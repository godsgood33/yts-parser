$(function () {
    $('#download').click(updateDownload);
    $('#search').autocomplete({
        source: '/query.php',
        minLength: 3,
        select: function (e, ui) {
            log('selected: ' + ui.item.value + ' aka ' + ui.item.id);
        }
    });
});

function updateDownload() {
    $.ajax('/query.php', {
        data: {
            action: 'updateDownload',
            title: $('#title').val(),
            year: $('#year').val(),
            download: $('#download').is(':checked')
        },
        success: function (res) {
            console.debug(res);
        },
        error: function (xhr, error, msg) {
            console.error(msg);
            console.error(error);
        },
        method: 'post',
        dataType: 'json'
    });
}

function autoComplete() {
    $.ajax('/query.php', {
        data: {
            action: 'autoComplete',
            search: $('#search').val()
        },
        success: function (res) {
            console.debug(res);
        },
        method: 'post',
        dataType: 'json'
    });
}