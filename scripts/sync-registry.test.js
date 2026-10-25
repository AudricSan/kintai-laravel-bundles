// Tests de scripts/sync-registry.js : épinglage des commits de tags `{slug}-vX.Y.Z`.
// Lancés en CI : node --test scripts/

const test = require("node:test");
const assert = require("node:assert/strict");

const { pinCommits, sortedCommits, fetchTagCommits, versionOf, versionsByChannel, localBundles } = require("./sync-registry.js");

const P = "timeoff-v";
const SHA_A = "a".repeat(40);
const SHA_B = "b".repeat(40);
const SHA_C = "c".repeat(40);

const release = (tag) => ({ tag_name: tag, target_commitish: "main", prerelease: false });

test("épingle le commit du tag pour une version encore jamais épinglée", () => {
  const out = pinCommits([release("timeoff-v1.0.0")], { "timeoff-v1.0.0": SHA_A }, undefined, P);

  assert.deepEqual(out.commits, { "1.0.0": SHA_A });
  assert.deepEqual(out.conflicts, []);
  assert.deepEqual(out.unpinnable, []);
  assert.equal(out.releases.length, 1);
});

test("conserve une empreinte existante quand le tag n'a pas bougé", () => {
  const out = pinCommits([release("timeoff-v1.0.0")], { "timeoff-v1.0.0": SHA_A }, { "1.0.0": SHA_A }, P);

  assert.deepEqual(out.commits, { "1.0.0": SHA_A });
  assert.deepEqual(out.conflicts, []);
});

test("TAG DÉPLACÉ : signale le conflit et ne modifie JAMAIS l'empreinte existante", () => {
  const out = pinCommits([release("timeoff-v1.0.0")], { "timeoff-v1.0.0": SHA_B }, { "1.0.0": SHA_A }, P);

  assert.deepEqual(out.conflicts, [{ version: "1.0.0", pinned: SHA_A, actual: SHA_B }]);
  assert.equal(out.commits["1.0.0"], SHA_A, "l'empreinte épinglée doit rester la première");
});

test("un conflit sur une version n'empêche pas d'épingler les autres", () => {
  const out = pinCommits(
    [release("timeoff-v1.0.0"), release("timeoff-v1.1.0")],
    { "timeoff-v1.0.0": SHA_B, "timeoff-v1.1.0": SHA_C },
    { "1.0.0": SHA_A }, P);

  assert.equal(out.conflicts.length, 1);
  assert.equal(out.commits["1.1.0"], SHA_C);
});

test("une release dont le tag est introuvable n'est jamais listée sans empreinte", () => {
  const out = pinCommits([release("timeoff-v1.0.0"), release("timeoff-v1.1.0")], { "timeoff-v1.0.0": SHA_A }, {}, P);

  assert.deepEqual(out.unpinnable, ["1.1.0"]);
  assert.deepEqual(out.releases.map((r) => r.tag_name), ["timeoff-v1.0.0"]);
  assert.deepEqual(Object.keys(out.commits), ["1.0.0"]);
});

test("une empreinte déjà épinglée reste listable même si le tag a disparu", () => {
  // On ne perd pas une version connue parce qu'un appel API a renvoyé moins de tags ; l'installateur
  // comparera de toute façon le ZIP à l'empreinte.
  const out = pinCommits([release("timeoff-v1.0.0")], {}, { "1.0.0": SHA_A }, P);

  assert.equal(out.commits["1.0.0"], SHA_A);
  assert.deepEqual(out.unpinnable, []);
});

test("les empreintes de versions qui ne sont plus des releases sont abandonnées", () => {
  const out = pinCommits([release("timeoff-v1.1.0")], { "timeoff-v1.1.0": SHA_C }, { "1.0.0": SHA_A, "1.1.0": SHA_C }, P);

  assert.deepEqual(Object.keys(out.commits), ["1.1.0"]);
});

test("sortedCommits trie par version décroissante (diffs stables)", () => {
  const out = sortedCommits({ "1.0.9": SHA_A, "1.0.10": SHA_B, "1.2.0": SHA_C });

  assert.deepEqual(Object.keys(out), ["1.2.0", "1.0.10", "1.0.9"]);
});

// --- fetchTagCommits, avec un fetch simulé --------------------------------------------------------

function withFetch(routes, fn) {
  const original = global.fetch;
  global.fetch = async (url) => {
    for (const [pattern, response] of routes) {
      if (url.includes(pattern)) {
        const { status = 200, body } = typeof response === "function" ? response(url) : response;
        return { ok: status >= 200 && status < 300, status, statusText: String(status), json: async () => body };
      }
    }
    throw new Error(`fetch inattendu : ${url}`);
  };
  return Promise.resolve(fn()).finally(() => {
    global.fetch = original;
  });
}

const repo = { owner: "o", repo: "r" };

test("fetchTagCommits : tag léger -> commit directement", () =>
  withFetch(
    [["matching-refs", { body: [{ ref: "refs/tags/timeoff-v1.0.0", object: { type: "commit", sha: SHA_A } }] }]],
    async () => assert.deepEqual(await fetchTagCommits(repo, P), { "timeoff-v1.0.0": SHA_A })
  ));

test("fetchTagCommits : tag annoté -> déréférencé jusqu'au commit", () =>
  withFetch(
    [
      ["matching-refs", { body: [{ ref: "refs/tags/timeoff-v1.0.0", object: { type: "tag", sha: SHA_B } }] }],
      [`git/tags/${SHA_B}`, { body: { object: { type: "commit", sha: SHA_A } } }],
    ],
    async () => assert.deepEqual(await fetchTagCommits(repo, P), { "timeoff-v1.0.0": SHA_A })
  ));

test("fetchTagCommits : ignore les tags qui ne sont pas {slug}-vX.Y.Z", () =>
  withFetch(
    [
      [
        "matching-refs",
        {
          body: [
            { ref: "refs/tags/timeoff-v1.0.0", object: { type: "commit", sha: SHA_A } },
            { ref: "refs/tags/timeoff-v1.0.0-beta", object: { type: "commit", sha: SHA_B } },
            { ref: "refs/tags/timeoff-vfoo", object: { type: "commit", sha: SHA_C } },
          ],
        },
      ],
    ],
    async () => assert.deepEqual(await fetchTagCommits(repo, P), { "timeoff-v1.0.0": SHA_A })
  ));

test("fetchTagCommits : dépôt sans tags (404) -> aucun commit", () =>
  withFetch([["matching-refs", { status: 404, body: {} }]], async () => assert.deepEqual(await fetchTagCommits(repo, P), {})));

test("fetchTagCommits : une erreur d'API remonte (le bundle est alors ignoré, pas épinglé de travers)", () =>
  withFetch([["matching-refs", { status: 500, body: {} }]], async () =>
    assert.rejects(() => fetchTagCommits(repo, P), /GitHub API error \(tags\)/)
  ));

test("fetchTagCommits : ignore un objet qui n'est pas un commit valide", () =>
  withFetch(
    [["matching-refs", { body: [{ ref: "refs/tags/timeoff-v1.0.0", object: { type: "commit", sha: "not-a-sha" } }] }]],
    async () => assert.deepEqual(await fetchTagCommits(repo, P), {})
  ));

// --- Dépôt de plusieurs bundles --------------------------------------------------------------------

test("versionOf : seul le préfixe du bundle compte (timeoff-v ne prend pas timeoff-extra-v)", () => {
  assert.equal(versionOf("timeoff-v3.0.0", P), "3.0.0");
  assert.equal(versionOf("timeoff-extra-v3.0.0", P), null);
  assert.equal(versionOf("v3.0.0", P), null);
  assert.equal(versionOf("timeoff-v3.0", P), null);
});

test("pinCommits ignore les releases d'un autre bundle du dépôt", () => {
  const out = pinCommits([release("timeoff-v3.0.0"), release("messaging-v3.0.0")], { "timeoff-v3.0.0": SHA_A }, {}, P);

  assert.deepEqual(out.commits, { "3.0.0": SHA_A });
  assert.deepEqual(out.unpinnable, []);
});

test("versionsByChannel : canal déduit de la branche source et du flag prerelease", () => {
  const out = versionsByChannel(
    [
      { tag_name: "timeoff-v3.1.1", target_commitish: "alpha", prerelease: true },
      { tag_name: "timeoff-v3.1.2", target_commitish: "beta", prerelease: true },
      { tag_name: "timeoff-v3.0.0", target_commitish: "main", prerelease: false },
    ],
    P
  );

  assert.deepEqual(out, { release: ["3.0.0"], beta: ["3.1.2", "3.0.0"], alpha: ["3.1.2", "3.1.1", "3.0.0"] });
});

test("localBundles liste chaque dossier qui contient un bundle.json", () => {
  const bundles = localBundles(require("path").join(__dirname, ".."));

  assert.ok(bundles.length >= 13);
  for (const b of bundles) {
    assert.equal(b.directory, b.slug, "le dossier porte le slug du bundle");
    assert.ok(b.name && b.description);
  }
});
