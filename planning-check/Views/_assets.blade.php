@php /** Feuille de style du bundle (fenêtres, barre de correction, historique). */ @endphp
@if (($_pcCss = bundle_asset('planning-check', 'css/planning-check.css')) !== null)
<link rel="stylesheet" href="{{ $_pcCss }}">
@php endif; unset($_pcCss); @endphp
