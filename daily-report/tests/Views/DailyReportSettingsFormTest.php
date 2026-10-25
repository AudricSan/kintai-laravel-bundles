<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Views;

use PHPUnit\Framework\TestCase;

/**
 * Chaque réglage enregistré par DailyReportController::saveSettings() doit avoir son champ dans le formulaire.
 *
 * Régression d'origine : le formulaire n'avait aucun champ pour l'heure de validation automatique ni pour les heures de
 * rappel. Comme le contrôleur lit ces valeurs dans la requête, chaque enregistrement effaçait l'auto-validation et
 * remettait les rappels à 15:00 et 18:00, sans que personne puisse les régler.
 */
final class DailyReportSettingsFormTest extends TestCase
{
    private string $view;
    private string $controller;

    protected function setUp(): void
    {
        $this->view       = (string) file_get_contents(dirname(__DIR__, 2) . '/Views/daily-report-settings.php');
        $this->controller = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/Web/DailyReportController.php');
    }

    /** @return list<string> noms des champs lus par saveSettings() */
    private function savedFields(): array
    {
        $start = strpos($this->controller, 'public function saveSettings');
        $this->assertNotFalse($start, 'saveSettings() introuvable');
        $body = substr($this->controller, $start, 3500);

        preg_match_all('/\$data\[\'([a-z_]+)\'\]/', $body, $m);

        return array_values(array_unique($m[1]));
    }

    public function testEverySavedSettingHasAFormField(): void
    {
        $fields = $this->savedFields();
        $this->assertNotEmpty($fields);

        foreach ($fields as $name) {
            // Un champ émis par le composant UserPicker n'a pas de balise name="…" dans le source de la vue.
            $this->assertMatchesRegularExpression(
                '/name="' . preg_quote($name, '/') . '(\[\])?"|UserPicker::make\(\'' . preg_quote($name, '/') . '\'/',
                $this->view,
                "Le réglage « $name » est enregistré par le contrôleur mais n'a aucun champ dans le formulaire."
            );
        }
    }

    /**
     * Régression 2.1.0 : la vue lisait $recipientIds et $legacyRecipients sans les définir, la page des réglages
     * répondait 500 (TypeError dans UserPicker::selected()). Les tests unitaires ne rendent pas la vue : on vérifie ici
     * que chaque variable utilisée est soit fournie par le contrôleur, soit définie en tête de vue.
     */
    public function testEveryVariableReadByTheViewIsDefined(): void
    {
        $provided = ['store', 'settings', 'authUser', 'membership', 'errors', 'candidates', 'BASE_URL'];

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $this->view, $used);
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=[^=>]|as\s+\$([a-zA-Z_][a-zA-Z0-9_]*)|\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=>|=>\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\)/', $this->view, $defined);

        $known = array_merge($provided, ['this'], array_filter(array_merge($defined[1], $defined[2], $defined[3], $defined[4])));
        $undefined = array_values(array_diff(array_unique($used[1]), $known));

        $this->assertSame([], $undefined, 'Variable(s) lue(s) par la vue sans être définie(s) : ' . implode(', ', $undefined));
    }

    public function testTheAutoValidationAndReminderTimesAreEditable(): void
    {
        foreach (['auto_validate_time', 'reminder_time', 'no_report_time'] as $name) {
            $this->assertStringContainsString('name="' . $name . '"', $this->view);
            $this->assertMatchesRegularExpression('/type="time"[^>]*name="' . $name . '"/s', $this->view);
        }
    }
}
