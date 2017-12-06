(function () {
    var newLine = document.createElement('tr');
    loadTableData = function () {
        var table = document.querySelector('table[id^=lists_] tbody'),
            prices = {},
            row = '';

        if (table) {
            if(newLine) newLine.innerHTML = '';
            table.after(newLine);
            table.querySelectorAll('tr').forEach(function (tr) {
                tr.querySelectorAll('.main-grid-cell').forEach(function (td, i) {
                    var price = td.innerText.match(/(\d+\.\d{0,2})[^\d]*$/);
                    if (price) {
                        if (!prices[i]) prices[i] = 0;
                        prices[i] += +price[1];
                    }
                })
            });

            table.querySelector('tr').querySelectorAll('.main-grid-cell').forEach(function (td, i) {
                var td = newLine.insertCell();
                if (prices[i]) td.innerHTML = '<b class="main-grid-cell-content" data-prevent-default="true">€' + prices[i] + '</b>';
            });
        }
    };

    loadTableData();
    BX.ajax.xhrSuccess = function (e) {
        setTimeout(loadTableData, 1);
        return e.status >= 200 && e.status < 300 || e.status === 304 || e.status === 1223 || e.status === 0;
    };
})();