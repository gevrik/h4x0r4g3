$.fn.dataTable.render.actionButtons = function () {
    return function ( data, type, row ) {
        var actionHtml = '';
        for (var i = 0, len = data.length; i < len; i++) {
            actionHtml += '<a href="/' + data[i].route + '/' + data[i].action + '/' + data[i].id + '" class="btn btn-sm ' + data[i].class +  '"><i class="fa fa-fw ' + data[i].icon + '"></i></a> ';
        }
        return actionHtml;
    };
};
