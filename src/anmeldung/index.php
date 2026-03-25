<?php include '../includes/header.php'; ?>
<div class="text">
    <h1>Anmeldung</h1>
    <p>Anmeldung lief bis 28.02. und ist abgeschlossen. Vielen Dank für eure Anmeldungen!</p>
    <p>Falls ihr Änderungen habt, könnt ihr uns gerne kontaktieren. <a href="mailto:oliver@schoettner.rocks">oliver@schoettner.rocks</a></p>
    <p>Falls ihr nur hier seid, um nochmal nachzusehen, was es zu essen gibt hier nochmal die Übersicht:</p>
    <ul>
        <li>Spanferkel</li>
        <li>Steckerlfisch</li>
        <li>Reispfanne mit Gemüse (vegetarisch)</li>
    </ul>
    <!--

    <p>Eure Essenwünsche teilt ihr uns für die bessere Planung mit. Niemand wird am Tag kontrollieren, ob ihr auch
        wirklich nur das esst, was ihr gewählt habt. Dementsprechend könnt ihr auch alle drei Möglichkeiten
        auswählen, wenn ihr alles drei probieren wollt.</p>
        <p>Der Partyabend soll für alle sein - und ihr wisst, wie wichtig Musik für uns ist. Deshalb wollen wir zwingend wissen, welchen einen Song ihr euch für den Abend wünscht.</p>
        <p>Wer uns für den Nachmittagskaffee einen Kuchen oder herzhaften Snack mitbringen mag, darf das hier auch gerne angeben. Wir freuen uns über jede Unterstützung!</p>

    <div id="message" style="display:none; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;"></div>

    <form id="registrationForm" method="post" action="register.php">
        <div>
            <label for="vorname">Vorname *</label><br>
            <input type="text" id="vorname" name="vorname" autocomplete="given-name" required>
        </div>
        <div>
            <label for="nachname">Nachname *</label><br>
            <input type="text" id="nachname" name="nachname" autocomplete="family-name" required>
        </div>

        <div class="group">
            <label>Essen (Mehrfachauswahl möglich)</label>
            <div>
                <input type="checkbox" id="essen_schwein" name="essen[]" value="Schwein">
                <label for="essen_schwein">Spanferkel</label>
            </div>
            <div>
                <input type="checkbox" id="essen_fisch" name="essen[]" value="Fisch">
                <label for="essen_fisch">Steckerlfisch</label>
            </div>
            <div>
                <input type="checkbox" id="essen_veg" name="essen[]" value="Vegetarisch">
                <label for="essen_veg">Reispfanne mit Gemüse</label>
            </div>
        </div>

        <div>
            <label for="song">Songwunsch *</label><br>
            <input type="text" id="song" name="song" required>
        </div>

        <div>
            <label for="kuchen">Ich bringe folgenden Kuchen / Snack fürs Buffet (optional)</label><br>
            <input type="text" id="kuchen" name="kuchen">
        </div>

        <div>
            <label for="allergien">Allergien / Unverträglichkeiten (optional)</label><br>
            <input type="text" id="allergien" name="allergien">
        </div>

        <div class="group">
            <label>Transport zum Hotel</label>
            <div>
                <input type="radio" id="transport_nein" name="transport" value="nein" checked>
                <label for="transport_nein">Nein</label>
            </div>
            <div>
                <input type="radio" id="transport_ja" name="transport" value="ja">
                <label for="transport_ja">Ja</label>
            </div>

            <div id="transport-details" style="display:none; margin-top:0.5rem;">
                <label for="transport_welches">Welches Hotel / zusätzliche Infos</label><br>
                <input type="text" id="transport_welches" name="transport_welches">
            </div>
        </div>

        <div class="group">
            <label for="sonstiges">Was wir sonst noch wissen müssen (optional)</label><br>
            <textarea id="sonstiges" name="sonstiges" rows="4"></textarea>
        </div>

        <p>Wir wollen den Tag mit euch verbringen, eure Kinder haben euch den Rest das Jahres. Also organisiert euch
            einen Babysitter oder fragt die Opas und Omas und lasst mit uns die Sau raus.</p>

        <div>
            <input class="button" type="submit" value="Anmelden" />
        </div>
    </form>

    <script>
    (function() {
        const yes = document.getElementById('transport_ja');
        const no = document.getElementById('transport_nein');
        const details = document.getElementById('transport-details');

        function toggle() {
            details.style.display = yes.checked ? 'block' : 'none';
        }

        yes.addEventListener('change', toggle);
        no.addEventListener('change', toggle);
        toggle();

        // Formular-Handling mit AJAX
        const form = document.getElementById('registrationForm');
        const messageDiv = document.getElementById('message');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Senden-Button deaktivieren
            const submitBtn = form.querySelector('input[type="submit"]');
            const originalValue = submitBtn.value;
            submitBtn.disabled = true;
            submitBtn.value = 'Wird gesendet...';

            // Formular-Daten sammeln
            const formData = new FormData(form);

            // AJAX-Request
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.style.display = 'block';
                
                if (data.success) {
                    messageDiv.style.backgroundColor = '#d4edda';
                    messageDiv.style.color = '#155724';
                    messageDiv.style.border = '1px solid #c3e6cb';
                    messageDiv.textContent = data.message;
                    form.reset();
                } else {
                    messageDiv.style.backgroundColor = '#f8d7da';
                    messageDiv.style.color = '#721c24';
                    messageDiv.style.border = '1px solid #f5c6cb';
                    messageDiv.textContent = data.message;
                }

                // Scroll zur Nachricht
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(error => {
                messageDiv.style.display = 'block';
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.textContent = 'Es gab einen Fehler. Bitte versuche es später erneut.';
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.value = originalValue;
            });
        });
    })();
    </script>
-->
</div>

<?php include '../includes/footer.php'; ?>