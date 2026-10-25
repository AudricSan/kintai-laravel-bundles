<?php
/**
 * Champs « Shift ouvert » du formulaire de shift (section déclarée au Core, contrat 1.17) : note et case de
 * publication à la bourse. Enregistrés par le Core (colonnes is_open et open_shift_note de shifts).
 *
 * @var array $shift
 */
?>
    <div class="form-group">
        <label class="form-label"><?= __('shift_claim_open_shift_note_label') ?></label>
        <textarea name="open_shift_note" class="form-control" rows="2"
                  placeholder="<?= __('shift_claim_open_shift_note_placeholder') ?>"><?= htmlspecialchars($shift['open_shift_note'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('shift_claim_open_shifts') ?></label>
        <label class="toggle-label">
            <input type="hidden" name="is_open" value="0">
            <input type="checkbox" name="is_open" value="1" <?= (int)($shift['is_open'] ?? 0) ? 'checked' : '' ?>>
            <?= __('shift_claim_publish_to_bourse') ?>
        </label>
    </div>
