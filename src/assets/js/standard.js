document.addEventListener('DOMContentLoaded', function() {
    const burger = document.querySelector('.burger');
    const navigationContainer = document.querySelector('.navigation-container');
    
    if (burger && navigationContainer) {
        burger.addEventListener('click', function() {
            navigationContainer.classList.toggle('active');
        });
    }

    // Countdown
    const countdownElement = document.getElementById('countdown');
    if (countdownElement) {

        // helper: get offset (minutes) of given timeZone for a Date instance
        function getTimeZoneOffsetMinutes(timeZone, date) {
            const dtf = new Intl.DateTimeFormat('en-US', {
                timeZone,
                hour12: false,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const parts = dtf.formatToParts(date);
            const map = {};
            parts.forEach(p => { if (p.type !== 'literal') map[p.type] = p.value; });
            const asUTC = Date.UTC(
                Number(map.year),
                Number(map.month) - 1,
                Number(map.day),
                Number(map.hour),
                Number(map.minute),
                Number(map.second)
            );
            return (date.getTime() - asUTC) / 60000; // minutes
        }

        // build a timestamp for the given wall-clock in a specific time zone (accounts for DST)
        function zonedTimestamp(year, month, day, hour, minute, second, timeZone) {
            const wallUTC = Date.UTC(year, month - 1, day, hour, minute, second);
            const offsetMinutes = getTimeZoneOffsetMinutes(timeZone, new Date(wallUTC));
            return wallUTC + offsetMinutes * 60000;
        }

        // Wedding: 2026-07-04 14:00 in Europe/Berlin (DST handled automatically)
        const weddingTimestamp = zonedTimestamp(2026, 7, 4, 14, 0, 0, 'Europe/Berlin');

        function updateCountdown() {
            const now = Date.now();
            const distance = weddingTimestamp - now;

            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

                document.querySelector('#countdown .days').textContent = days;
                document.querySelector('#countdown .hours').textContent = hours;
                document.querySelector('#countdown .minutes').textContent = minutes;
            } else {
                countdownElement.textContent = 'Der große Tag ist da!';
            }
        }

        // Initial update
        updateCountdown();
        
        // Update every minute
        setInterval(updateCountdown, 60000);
    }
});