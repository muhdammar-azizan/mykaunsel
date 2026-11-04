// The counselor's Schedule calendar (resources/views/counselors/dashboard/planner.blade.php).
// READ ONLY in this step: it draws what the server sends (CounselorScheduleEvents) and, when an item is clicked, shows its details with the
// actions that already exist (remove a time that never had a booking, remove a block, open a booking). Nothing is moved by dragging yet.
//
// All text that comes from the server is put in with textContent, never as HTML. All the markup and its classes live in the Blade page (a
// <template> and a <dialog>), because Tailwind only knows the classes it finds in the Blade files.
import { Calendar } from '@fullcalendar/core';
import timeGridPlugin from '@fullcalendar/timegrid';
import luxon3Plugin from '@fullcalendar/luxon3';

const element = document.getElementById('schedule-calendar');

if (element) {
    const dialog = document.getElementById('schedule-event');
    const message = document.getElementById('schedule-message');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const narrow = window.matchMedia('(max-width: 767px)').matches;

    const say = (text) => {
        if (message) {
            message.textContent = text;
            message.hidden = text === '';
        }
    };

    const line = (tag, className, text) => {
        const node = document.createElement(tag);
        node.className = className;
        node.textContent = text;

        return node;
    };

    const fill = (id, text) => {
        const node = dialog.querySelector(`[data-field="${id}"]`);

        if (node) {
            node.textContent = text ?? '';
            node.closest('[data-row]')?.toggleAttribute('hidden', ! text);
        }
    };

    const openDetails = (event) => {
        const p = event.extendedProps;

        fill('kind', p.kindLabel);
        fill('when', p.when);
        fill('detail', p.detail);
        fill('hidden', p.hiddenLabel ? `${p.hiddenLabel}. ${p.hiddenExplain ?? ''}` : '');
        dialog.querySelector('[data-field="title"]').textContent = event.title;

        const actions = dialog.querySelector('[data-actions]');
        actions.replaceChildren();

        for (const action of p.actions ?? []) {
            if (action.method === 'GET') {
                const link = dialog.querySelector('template[data-link]').content.firstElementChild.cloneNode(true);
                link.href = action.url;
                link.textContent = action.label;
                actions.append(link);
            } else {
                const form = dialog.querySelector('template[data-form]').content.firstElementChild.cloneNode(true);
                form.action = action.url;
                form.querySelector('[name="_token"]').value = csrf;
                form.querySelector('[name="_method"]').value = action.method;
                form.querySelector('button').textContent = action.label;
                actions.append(form);
            }
        }

        if (! (p.actions ?? []).length) {
            actions.append(line('p', 'text-[13px] text-brand-ink/65', p.kind === 'session'
                ? dialog.dataset.sessionNote
                : dialog.dataset.nothingToDo));
        }

        dialog.showModal();
    };

    const calendar = new Calendar(element, {
        plugins: [timeGridPlugin, luxon3Plugin],
        // Malaysia time for everyone, whatever zone the browser is in (the server sends each time with its +08:00 offset).
        timeZone: element.dataset.timezone,
        initialView: narrow ? 'timeGridDay' : 'timeGridWeek',
        initialDate: element.dataset.initialDate || undefined,
        firstDay: 1,
        nowIndicator: true,
        allDaySlot: true,
        allDayText: 'All day',
        // The hours drawn are the same ones the time pickers offer (config/counselors.php, sent by the page).
        slotMinTime: element.dataset.slotMin || '06:00:00',
        slotMaxTime: element.dataset.slotMax || '23:00:00',
        scrollTime: '08:00:00',
        slotDuration: '00:30:00',
        slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
        expandRows: true,
        height: '100%',
        editable: false,
        selectable: false,
        eventInteractive: true,
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridWeek,timeGridDay' },
        buttonText: { today: 'Today', week: 'Week', day: 'Day' },
        events: {
            url: element.dataset.eventsUrl,
            failure: () => say(element.dataset.errorText),
        },
        loading: (isLoading) => {
            element.setAttribute('aria-busy', isLoading ? 'true' : 'false');

            if (! isLoading) {
                say('');
            }
        },
        eventContent: (arg) => {
            if (arg.event.display === 'background') {
                return { domNodes: [] };
            }

            const p = arg.event.extendedProps;
            const box = document.createElement('div');
            box.className = 'sc-body';
            box.append(line('div', 'sc-time', arg.timeText));
            box.append(line('div', 'sc-title', arg.event.title));

            // The reason is words, not only a colour.
            if (p.hiddenLabel) {
                box.append(line('div', 'sc-flag', p.hiddenLabel));
            }

            return { domNodes: [box] };
        },
        eventDidMount: (info) => {
            if (info.event.display === 'background') {
                return;
            }

            const p = info.event.extendedProps;
            info.el.setAttribute('aria-label', [p.kindLabel, p.when, p.detail, p.hiddenLabel].filter(Boolean).join('. '));
            info.el.setAttribute('title', [info.event.title, p.when].filter(Boolean).join(' · '));
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            openDetails(info.event);
        },
    });

    calendar.render();

    dialog?.addEventListener('click', (e) => {
        // A click on the dark area around the box closes it.
        if (e.target === dialog) {
            dialog.close();
        }
    });
}
