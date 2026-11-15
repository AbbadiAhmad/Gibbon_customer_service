/*
 * Service Desk board: drag a ticket card to another stage column.
 * Dropping fills in the hidden form #boardMove (built by Gibbon, with its CSRF token) and sends it; the server
 * moves the ticket, runs the stage's automation, and shows the board again.
 * Listeners sit on the document, so they keep working when Gibbon swaps the page content without a full reload.
 */
(function () {
    if (window.serviceDeskBoardReady) return;
    window.serviceDeskBoardReady = true;

    var dragged = null;

    function columnOf(target) {
        return target && target.closest ? target.closest('.serviceDesk-column[data-stage]') : null;
    }

    document.addEventListener('dragstart', function (event) {
        var card = event.target.closest ? event.target.closest('.serviceDesk-ticketCard.movable') : null;
        if (!card) return;
        dragged = card;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.ticket);
    });

    document.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('dragging');
        dragged = null;
        document.querySelectorAll('.serviceDesk-column.over').forEach(function (column) {
            column.classList.remove('over');
        });
    });

    document.addEventListener('dragover', function (event) {
        var column = columnOf(event.target);
        if (!dragged || !column) return;
        event.preventDefault();
        document.querySelectorAll('.serviceDesk-column.over').forEach(function (other) {
            if (other !== column) other.classList.remove('over');
        });
        column.classList.add('over');
    });

    document.addEventListener('drop', function (event) {
        var column = columnOf(event.target);
        if (!dragged || !column) return;
        event.preventDefault();

        var form = document.getElementById('boardMove');
        var stage = column.dataset.stage;
        if (!form || stage === dragged.dataset.stage) return;

        var board = column.closest('.serviceDesk-board');
        if (column.dataset.automation === '1' && !window.confirm(board.dataset.confirm)) return;

        form.querySelector('[name=gibbonServiceDeskTicketID]').value = dragged.dataset.ticket;
        form.querySelector('[name=gibbonServiceDeskStageID]').value = stage;
        column.querySelector('.serviceDesk-cards').appendChild(dragged);
        form.submit();
    });
})();
