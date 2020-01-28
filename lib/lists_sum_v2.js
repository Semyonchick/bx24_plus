calculateTable();
setInterval(calculateTable, 1000);

function calculateTable () {
  var headers = document.querySelectorAll('.main-grid-table thead th');
  for (var i in headers) {
    var header = headers[i];
    if (header && header.innerText) {
      var text = header.innerText.toLowerCase();
      if (text.indexOf('сумма') > -1 || text.indexOf('цена') > -1) {
        calculateField(header);
      }
    }
  }
}

function calculateField (field) {
  var list = field.parentNode.getElementsByTagName('th');
  var number;
  for (var i in list) if (list[i] === field) number = i;
  if (!number) return;

  var total = 0;
  var unit = '';

  var tr = field.parentNode.parentNode.parentNode.getElementsByClassName('main-grid-row-body');
  for (var n in tr) {
    var td = tr[n].children;
    if (td) {
      var parse = td[number].innerText.match(/^([\d\,\.]+)\s?([A-ZА-Яa-zа-я\.]*?)$/i);
      if (parse) {
        total += parseFloat(parse[1].replace(/\,/g, ''));
        unit = parse[2];
      }
    }
  }

  field.title = field.dataset.total = total + ' ' + unit;
}

var styles = '';
styles += '[data-total]:after{content: attr(data-total);font-size: 11px;position: absolute;bottom: 0;left: -11px;width: 100%;text-align: center;color: lightslategrey;}';
document.head.insertAdjacentHTML("beforeend", '<style>'+styles+'</style>');
