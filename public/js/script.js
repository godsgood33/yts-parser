$(function () {
    $('#download').click(updateDownload);
    $('.download').click(download);
    $('#search').change(autoComplete);
});

function download() {
    $.ajax('/query.php', {
        data: {
            action: 'download',
            title: $(this).data('title'),
            year: $(this).data('year')
        },
        success: function (res) {
            if (!res?.torrentName) return;
            alert('Downloading ' + res.torrentName);
        },
        error: function (xhr, error, msg) {
            console.error(msg);
        },
        method: 'post',
        dataType: 'json'
    });
}

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
    if ($('#search').val().length <= 2) return;

    $.ajax('/query.php', {
        data: {
            action: 'autoComplete',
            term: $('#search').val()
        },
        success: function (res) {
            console.log(res);
        },
        error: function (xhr, status, error) {
            console.error(error);
        },
        method: 'post',
        dataType: 'json'
    });
}