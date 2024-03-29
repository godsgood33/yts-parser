$(function () {
    $('#download').click(updateDownload);
    $('.download').click(function (e) {
        e.preventDefault();
        download(e)
    });
    $('.delete').click(function (e) {
        e.preventDefault();
        deleteMovie(e)
    });
    $('#search').keypress(search);
    ts = setTimeout(torrentStatus, 1000);
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

    $.ajax('/search', {
        data: {
            term: encodeURIComponent($('#search').val())
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

function download(e) {
    var btn = e.currentTarget;
    $.ajax('/download', {
        data: {
            title: $(btn).data('title'),
            year: $(btn).data('year'),
            quality: $(btn).data('quality')
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
}

function deleteMovie(e) {
    var btn = e.currentTarget;
    $.ajax('/deleteMovie', {
        data: {
            title: $(btn).data('title'),
            year: $(btn).data('year')
        },
        success: function (res) {
            if (res.success) {
                alert(res.title + ' (' + res.year + ') deleted');
                $(btn).parent().remove();
                $('#movieCount').text(res.movieCount);
                return;
            }
            console.log(res);
        },
        error: function (xhr, error, msg) {
            console.error(msg);
        },
        method: 'post',
        dataType: 'json'
    });
}

function torrentStatus() {
    $.ajax('/status', {
        data: {},
        success: function (res) {
            $('#downloadSize').html(res.downloadSize + "GB");
            $('#freeSpace').html(res.freeSpace + "GB");
            ts = setTimeout(torrentStatus, 1000);
        },
        error: function (xhr, error, msg) {
            console.error(msg);
        },
        method: 'post',
        dataType: 'json'
    });
}

function toggleStatus() {
    if (!ts) {
        ts = setTimeout(torrentStatus, 1000);
    } else {
        clearTimeout(ts);
        ts = null;
    }
}

function saveMovieCount() {
    $.ajax('/update-count', {
        data: {
            movieCount: $('#page-count').val()
        },
        success: function (res) {
            console.log(res);
        },
        error: function (xhr, error, msg) {
            console.error(msg);
        },
        method: 'post',
        dataType: 'json'
    });
}
