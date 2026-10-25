<?php
/**
 * Section « annuaire » du formulaire « Mes informations » du profil (point d'extension profileSections, contrat
 * 1.12) : apparaître dans l'annuaire, partager e-mail, téléphone, mobile. Le Core enregistre les cases déclarées par
 * TeamDirectoryBundle::profileSections() ; le champ caché « 0 » devant chaque case permet de la décocher.
 *
 * @var array $user
 */
?>
<div class="form-group">
    <label class="form-toggle form-toggle--labeled">
        <input type="hidden" name="show_in_directory" value="0">
        <input type="checkbox" name="show_in_directory" value="1" class="form-toggle__input"
               <?= (($user['show_in_directory'] ?? 1) != 0) ? 'checked' : '' ?>>
        <span class="form-toggle__track"></span>
        <span><?= __('team_directory_show_in_directory') ?></span>
    </label>
    <p class="form-hint"><?= __('team_directory_show_in_directory_hint') ?></p>
</div>
<div class="form-group">
    <p class="form-label"><?= __('team_directory_share_contact_info') ?></p>
    <p class="form-hint"><?= __('team_directory_share_contact_info_hint') ?></p>
    <div class="share-toggle-list">
        <label class="form-toggle form-toggle--labeled">
            <input type="hidden" name="share_email" value="0">
            <input type="checkbox" name="share_email" value="1" class="form-toggle__input"
                   <?= (($user['share_email'] ?? 0) != 0) ? 'checked' : '' ?>>
            <span class="form-toggle__track"></span>
            <span><?= __('email') ?></span>
        </label>
        <label class="form-toggle form-toggle--labeled">
            <input type="hidden" name="share_phone" value="0">
            <input type="checkbox" name="share_phone" value="1" class="form-toggle__input"
                   <?= (($user['share_phone'] ?? 0) != 0) ? 'checked' : '' ?>>
            <span class="form-toggle__track"></span>
            <span><?= __('phone') ?></span>
        </label>
        <label class="form-toggle form-toggle--labeled">
            <input type="hidden" name="share_mobile_phone" value="0">
            <input type="checkbox" name="share_mobile_phone" value="1" class="form-toggle__input"
                   <?= (($user['share_mobile_phone'] ?? 0) != 0) ? 'checked' : '' ?>>
            <span class="form-toggle__track"></span>
            <span><?= __('mobile_phone') ?></span>
        </label>
    </div>
</div>
