#!/usr/bin/env node
// Resynchronise registry.json (schema 2) avec les GitHub Releases de ce dépôt.
//
// Ce dépôt regroupe plusieurs bundles, un par dossier ({slug}/bundle.json). Les releases d'un bundle sont taguées
// `{slug}-vX.Y.Z` (voir .github/workflows/release.yml), et son repository_url désigne son dossier :
// https://github.com/{owner}/{repo}/tree/main/{slug}. Kintai résout alors la release `{slug}-v{version}`, télécharge
// son zipball et n'installe que ce dossier. La source de vérité de ce qui est installable reste les releases, jamais
// ce fichier : ce script ne fait que la refléter. Un bundle apparaît dans le registry à sa première release.
//
// Canal déterminé via target_commitish (la branche source de la release) et le flag prerelease, PAS en inspectant le
// tag — même règle que Kintai (GithubUpdateService / AppSettingsService::updateChannel()) :
//   - release : uniquement les releases publiées depuis `main`, non prerelease.
//   - beta    : les releases publiées depuis `main` ou `beta` (exclut `alpha`).
//   - alpha   : toutes les releases, canal le plus permissif.
//
// Épinglage des commits (champ `commits` de chaque bundle : { "3.0.0": "<sha du commit du tag>" }) : Kintai compare le
// ZIP qu'il télécharge à ce commit avant de l'installer. Le registry devient ainsi le point de confiance (ses
// changements passent par une PR relue), et non plus « ce vers quoi pointe un tag au moment de l'installation » — un
// tag déplacé vers un commit piégé serait refusé. Règle essentielle : une empreinte est posée UNE fois puis n'est
// JAMAIS modifiée automatiquement. Si un tag déjà épinglé pointe désormais vers un autre commit, le script échoue
// bruyamment (job en erreur, aucune PR) au lieu de « corriger » l'empreinte, ce qui annulerait toute la protection.

const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const REGISTRY_PATH = path.join(ROOT, "registry.json");
const CHANNELS = ["release", "beta", "alpha"];
const COMMIT_RE = /^[0-9a-f]{40}$/;
// Branche dont le repository_url d'un bundle désigne le dossier (purement informatif pour Kintai, qui ne lit que le dossier).
const URL_BRANCH = "main";

function escapeRegExp(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/** Version X.Y.Z d'un tag `{prefix}X.Y.Z`, ou null si le tag n'est pas de cette forme. */
function versionOf(tag, prefix) {
  const m = tag.match(new RegExp(`^${escapeRegExp(prefix)}(\\d+\\.\\d+\\.\\d+)$`));
  return m ? m[1] : null;
}

function compareVersionsDesc(a, b) {
  const pa = a.split(".").map(Number);
  const pb = b.split(".").map(Number);
  for (let i = 0; i < 3; i++) {
    if (pb[i] !== pa[i]) return pb[i] - pa[i];
  }
  return 0;
}

function matchesChannel(release, channel) {
  const branch = release.target_commitish || "";
  const isPrerelease = Boolean(release.prerelease);
  switch (channel) {
    case "alpha":
      return true;
    case "beta":
      return branch !== "alpha";
    default:
      return branch === "main" && !isPrerelease;
  }
}

function githubHeaders() {
  const headers = {
    Accept: "application/vnd.github+json",
    "User-Agent": "kintai-laravel-bundles-sync-registry",
  };
  if (process.env.GITHUB_TOKEN) {
    headers.Authorization = `Bearer ${process.env.GITHUB_TOKEN}`;
  }
  return headers;
}

/**
 * Commit vers lequel pointe chaque tag `{prefix}X.Y.Z` du dépôt, sous forme { "{prefix}X.Y.Z": "<sha 40 hex>" }.
 * Un tag « léger » pointe directement sur un commit ; un tag « annoté » pointe sur un objet tag qu'il faut
 * déréférencer pour atteindre le commit.
 */
async function fetchTagCommits({ owner, repo }, prefix) {
  const headers = githubHeaders();
  const refs = [];
  for (let page = 1; ; page++) {
    const res = await fetch(
      `https://api.github.com/repos/${owner}/${repo}/git/matching-refs/tags/${encodeURIComponent(prefix)}?per_page=100&page=${page}`,
      { headers }
    );
    if (res.status === 404) break;
    if (!res.ok) {
      throw new Error(`GitHub API error (tags) for ${owner}/${repo}: ${res.status} ${res.statusText}`);
    }
    const batch = await res.json();
    refs.push(...batch);
    if (batch.length < 100) break;
  }

  const commits = {};
  for (const ref of refs) {
    const tag = ref.ref.replace(/^refs\/tags\//, "");
    if (versionOf(tag, prefix) === null) continue;

    let object = ref.object;
    // Tag annoté : on suit la chaîne jusqu'au commit (quelques niveaux au plus en pratique).
    for (let depth = 0; object.type === "tag" && depth < 3; depth++) {
      const res = await fetch(`https://api.github.com/repos/${owner}/${repo}/git/tags/${object.sha}`, { headers });
      if (!res.ok) {
        throw new Error(`GitHub API error (annotated tag ${tag}) for ${owner}/${repo}: ${res.status} ${res.statusText}`);
      }
      object = (await res.json()).object;
    }
    if (object.type === "commit" && COMMIT_RE.test(object.sha)) {
      commits[tag] = object.sha;
    }
  }
  return commits;
}

/** Toutes les releases publiées (hors brouillons) du dépôt. */
async function fetchReleases({ owner, repo }) {
  const headers = githubHeaders();
  const releases = [];
  for (let page = 1; ; page++) {
    const res = await fetch(`https://api.github.com/repos/${owner}/${repo}/releases?per_page=100&page=${page}`, { headers });
    if (res.status === 404) break;
    if (!res.ok) {
      throw new Error(`GitHub API error for ${owner}/${repo}: ${res.status} ${res.statusText}`);
    }
    const batch = await res.json();
    if (batch.length === 0) break;
    releases.push(...batch.filter((r) => !r.draft));
    if (batch.length < 100) break;
  }
  return releases;
}

/**
 * Croise les releases d'un bundle avec le commit de leur tag et les empreintes déjà épinglées.
 * @returns {{commits: Object<string,string>, conflicts: Array, unpinnable: string[], releases: Array}}
 *   - commits : version -> sha à écrire (une empreinte existante est toujours conservée) ;
 *   - conflicts : versions déjà épinglées dont le tag pointe maintenant ailleurs ;
 *   - unpinnable : versions dont le tag est introuvable (jamais listées sans empreinte) ;
 *   - releases : les releases qui restent listables.
 */
function pinCommits(releases, tagCommits, existing, prefix) {
  const pinned = existing && typeof existing === "object" ? existing : {};
  const commits = {};
  const conflicts = [];
  const unpinnable = [];
  const listable = [];

  for (const release of releases) {
    const version = versionOf(release.tag_name, prefix);
    if (version === null) continue;
    const actual = tagCommits[release.tag_name];
    const previous = pinned[version];

    if (previous && actual && previous !== actual) {
      conflicts.push({ version, pinned: previous, actual });
      commits[version] = previous; // on ne touche jamais à une empreinte existante
      listable.push(release);
    } else if (previous || actual) {
      commits[version] = previous || actual;
      listable.push(release);
    } else {
      unpinnable.push(version);
    }
  }
  return { commits, conflicts, unpinnable, releases: listable };
}

/** @returns {{release: string[], beta: string[], alpha: string[]}} */
function versionsByChannel(releases, prefix) {
  const result = {};
  for (const channel of CHANNELS) {
    const versions = releases.filter((r) => matchesChannel(r, channel)).map((r) => versionOf(r.tag_name, prefix));
    versions.sort(compareVersionsDesc);
    result[channel] = versions;
  }
  return result;
}

/** Empreintes triées par version décroissante, pour des diffs stables. */
function sortedCommits(commits) {
  const out = {};
  for (const version of Object.keys(commits).sort(compareVersionsDesc)) {
    out[version] = commits[version];
  }
  return out;
}

/** Bundles de ce dépôt : chaque dossier de premier niveau qui contient un bundle.json. */
function localBundles(root) {
  return fs
    .readdirSync(root, { withFileTypes: true })
    .filter((d) => d.isDirectory() && fs.existsSync(path.join(root, d.name, "bundle.json")))
    .map((d) => {
      const manifest = JSON.parse(fs.readFileSync(path.join(root, d.name, "bundle.json"), "utf8"));
      return { directory: d.name, slug: manifest.slug, name: manifest.name, description: manifest.description };
    })
    .sort((a, b) => a.slug.localeCompare(b.slug));
}

function parseRepo(fullName) {
  const m = String(fullName || "").match(/^([^/\s]+)\/([^/\s]+)$/);
  if (!m) {
    throw new Error(`GITHUB_REPOSITORY must look like owner/repo, got ${JSON.stringify(fullName)}`);
  }
  return { owner: m[1], repo: m[2] };
}

async function main() {
  const repoInfo = parseRepo(process.env.GITHUB_REPOSITORY || "AudricSan/kintai-laravel-bundles");
  const registry = JSON.parse(fs.readFileSync(REGISTRY_PATH, "utf8"));
  const releases = await fetchReleases(repoInfo);
  let changed = false;
  const conflicts = [];

  for (const local of localBundles(ROOT)) {
    const prefix = `${local.directory}-v`;
    const own = releases.filter((r) => versionOf(r.tag_name, prefix) !== null);
    const existing = registry.bundles.find((b) => b.slug === local.slug);

    if (own.length === 0) {
      if (!existing) console.log(`${local.slug}: no ${prefix}X.Y.Z release yet; not listed.`);
      continue;
    }

    const tagCommits = await fetchTagCommits(repoInfo, prefix);
    const pinned = pinCommits(own, tagCommits, existing && existing.commits, prefix);
    for (const version of pinned.unpinnable) {
      console.warn(`Warning: ${local.slug} ${version} has a release but its tag cannot be resolved to a commit; not listed.`);
    }
    for (const c of pinned.conflicts) {
      conflicts.push({ slug: local.slug, ...c });
    }
    if (pinned.releases.length === 0) continue;

    const entry = {
      slug: local.slug,
      name: local.name,
      description: local.description,
      repository_url: `https://github.com/${repoInfo.owner}/${repoInfo.repo}/tree/${URL_BRANCH}/${local.directory}`,
      versions: versionsByChannel(pinned.releases, prefix),
      commits: sortedCommits(pinned.commits),
    };

    if (!existing) {
      registry.bundles.push(entry);
      console.log(`${local.slug}: added with ${JSON.stringify(entry.versions)}`);
      changed = true;
    } else if (JSON.stringify(existing) !== JSON.stringify({ ...existing, ...entry })) {
      console.log(`${local.slug}: ${JSON.stringify(existing.versions)} -> ${JSON.stringify(entry.versions)}`);
      Object.assign(existing, entry);
      changed = true;
    }
  }

  if (conflicts.length > 0) {
    // Un tag épinglé a bougé : possible compromission (ou re-tag légitime, à trancher à la main). On échoue AVANT
    // d'écrire quoi que ce soit : pas de PR automatique.
    for (const c of conflicts) {
      console.error(
        `::error::${c.slug} ${c.version}: le tag pointe maintenant vers ${c.actual} alors que le registry épingle ${c.pinned}. ` +
          "Le tag a été déplacé : vérifier le dépôt avant toute modification de registry.json."
      );
    }
    console.error(`${conflicts.length} tag(s) épinglé(s) ont changé de commit ; registry.json n'a pas été modifié.`);
    process.exit(1);
  }

  if (changed) {
    registry.schema_version = 2;
    registry.bundles.sort((a, b) => a.slug.localeCompare(b.slug));
    registry.updated_at = new Date().toISOString().replace(/\.\d+Z$/, "Z");
    fs.writeFileSync(REGISTRY_PATH, JSON.stringify(registry, null, 4) + "\n");
    console.log("registry.json updated.");
  } else {
    console.log("No version changes detected.");
  }

  // Indique au workflow si une PR est nécessaire.
  if (process.env.GITHUB_OUTPUT) {
    fs.appendFileSync(process.env.GITHUB_OUTPUT, `changed=${changed}\n`);
  }
}

module.exports = { pinCommits, sortedCommits, versionsByChannel, matchesChannel, fetchTagCommits, versionOf, localBundles };

if (require.main === module) {
  main().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
