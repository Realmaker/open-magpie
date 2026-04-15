<x-mail::message>
# Projekteinladung

**{{ $sharedBy->name }}** hat das Projekt **{{ $project->name }}** mit dir geteilt.

**Berechtigung:** {{ ucfirst($permission) }}

@if($permission === 'viewer')
Du kannst das Projekt und alle Inhalte (Events, Tasks, Dokumente) einsehen.
@elseif($permission === 'editor')
Du kannst Events, Tasks und Dokumente einsehen und erstellen.
@elseif($permission === 'admin')
Du hast vollen Zugriff auf das Projekt und kannst es auch mit anderen teilen.
@endif

<x-mail::button :url="$acceptUrl">
Einladung annehmen
</x-mail::button>

Der Einladungslink ist **7 Tage** gueltig.

Falls du diese Einladung nicht erwartet hast, kannst du diese E-Mail ignorieren.

Viele Gruesse,<br>
{{ config('app.name') }}
</x-mail::message>
