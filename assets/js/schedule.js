// UHA Portal — schedule interactions

(() => {
  document.addEventListener("DOMContentLoaded", () => {
    console.log("Schedule page loaded");

    // Collapse other months when one is opened
    document.querySelectorAll("details.sched-month").forEach(det => {
      det.addEventListener("toggle", () => {
        if (det.open) {
          document.querySelectorAll("details.sched-month").forEach(other => {
            if (other !== det) other.open = false;
          });
        }
      });
    });

    // Jump to today's games if an element with today's date id exists
    const today = new Date();
    const todayId = `day-${today.getMonth()+1}-${today.getDate()}`;
    const todaySection = document.getElementById(todayId);
    if (todaySection) {
      todaySection.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  });
})();
// ========= WEEK PAGER HANDLER =========
let currentWeekIndex = 0;

// load a week by index (0 = first week in JSON)
function renderWeek(index) {
  fetch('data/uploads/schedule.json')
    .then(r => r.json())
    .then(data => {
      const weeks = chunkScheduleByWeek(data); // <-- helper splits JSON into weeks
      if (index < 0 || index >= weeks.length) return;
      currentWeekIndex = index;

      const container = document.getElementById('schedule-container');
      container.innerHTML = '';

      const week = weeks[index];
      const weekDiv = document.createElement('div');
      weekDiv.className = 'schedule-week';

      const h2 = document.createElement('h2');
      h2.textContent = week.title;
      weekDiv.appendChild(h2);

      week.games.forEach(g => {
        const row = document.createElement('div');
        row.className = 'schedule-game';
        row.innerHTML = `
          <div class="teams">${g.home} vs ${g.away}</div>
          <div class="time">${g.time}</div>
          <div class="broadcasters">${g.broadcast || ''}</div>
        `;
        weekDiv.appendChild(row);
      });

      container.appendChild(weekDiv);
    });
}

// helper: chunk days into weeks
function chunkScheduleByWeek(data) {
  const weeks = [];
  let i = 0;
  for (const week of data.weeks) {
    weeks.push({
      title: week.title,
      games: week.games
    });
    i++;
  }
  return weeks;
}

// pager buttons
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('prevWeek').addEventListener('click', () => {
    renderWeek(currentWeekIndex - 1);
  });
  document.getElementById('nextWeek').addEventListener('click', () => {
    renderWeek(currentWeekIndex + 1);
  });

  renderWeek(0); // load first week on page load
});
