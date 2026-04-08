<?php include '../includes/header.php'; ?>

<div class="text">
    <h1>Getränkewünsche</h1>
    <p>Tragt bitte eure Lieblingsgetränke ein, damit wir die beliebtesten Optionen bereitstellen können. Egal ob es ein klassischer Cocktail, ein spezielles Bier oder ein alkoholfreies Getränk ist – wir möchten sicherstellen, dass für jeden Geschmack etwas dabei ist!</p>
    <p>Schaut euch auch die bereits eingetragenen Wünsche an und klickt auf <strong>+1</strong>, wenn ihr ein Getränk auch mögt. So können wir sehen, welche Getränke besonders beliebt sind.</p>
    <p>Direkt nach der Freien Trauung stehen für euch Erfrischungsgetränke bereit, aber auch hier hat die Bar schon offen. Es steht jederzeit alles zur Verfügung.</p>
    <p>Whiskey Cola um 15:05? Kein Problem! Bar ist auf!</p>
    <p>Wir freuen uns auf eure Wünsche und darauf, gemeinsam mit euch zu feiern!</p>
    <h2>Achtung</h2>
    <p>Diese Abstimmung läuft bis zwei Wochen vor der Hochzeit, danach werden die beliebtesten Getränke in die Planung aufgenommen. Also tragt eure Wünsche rechtzeitig ein und stimmt für eure Favoriten ab!</p>
    <h2>Getränkewunsch hinzufügen</h2>
    <div class="group">
        <label for="drink-input">Welches Getränk wünschst du dir?</label>
        <input type="text" id="drink-input" placeholder="z.B. Aperol Spritz" maxlength="100" autocomplete="off">
        <input type="submit" id="drink-submit" value="Wunsch einreichen">
    </div>
    <p id="drink-feedback" style="display:none; color: var(--highlight-color);"></p>

    <h2>Alle Wünsche</h2>
    <p>Klick auf <strong>+1</strong>, wenn du ein Getränk auch magst!</p>
    <ol id="drink-list">
        <li style="list-style:none; color: var(--shadow-color); font-style: italic;">Wird geladen…</li>
    </ol>
    <p id="drink-total">Gesamt 0 Stimmen</p>
</div>

<link rel="stylesheet" href="/getraenke/styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
<script src="/getraenke/script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>

<?php include '../includes/footer.php'; ?>