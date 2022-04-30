$(function () {
    $('#download').click(updateDownload);
    $('.download').on('click', function () {
        var btn = $(this);
        $.ajax('/query.php', {
            data: {
                action: 'download',
                title: $(this).data('title'),
                year: $(this).data('year')
            },
            success: function (res) {
                if (!res?.torrentName) return;
                alert('Downloading ' + res.torrentName);
                $(btn).parent().find('span').removeClass('have4k havefhd havehd');
                $(btn).parent().find('span').addClass(res.class);
                $(btn).remove();
            },
            error: function (xhr, error, msg) {
                console.error(msg);
            },
            method: 'post',
            dataType: 'json'
        });
    });
    $('#search').keypress(search);
});

function download(btn) {
    var text = $(btn);
    $.ajax('/query.php', {
        data: {
            action: 'download',
            title: $(this).data('title'),
            year: $(this).data('year')
        },
        success: function (res) {
            console.log(text);
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
            console.log(res);
        },
        error: function (xhr, error, msg) {
            console.error(msg);
            console.error(error);
        },
        method: 'post',
        dataType: 'json'
    });
}

function search(event) {
    if (event.which != 13) return;
    if ($('#search').val().length < 2) return;

    $.ajax('/query.php', {
        data: {
            action: 'search',
            term: $('#search').val()
        },
        success: function (res) {
            $('#container').children().remove();
            $('#container').append(res);
            $('.download').click(download);
        },
        error: function (xhr, status, error) {
            console.error(error);
        },
        method: 'post',
        dataType: 'html'
    });
}
