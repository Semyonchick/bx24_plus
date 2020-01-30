var courses;
var columns;

loadCourses();
addStyles();
calculateTable();

if (window.BX) BX.addCustomEvent('onAjaxSuccess', calculateTable);
else setTimeout(function () {
  calculateTable();
  if (window.BX) BX.addCustomEvent('onAjaxSuccess', calculateTable);
}, 1000);

// setInterval(calculateTable, 1000);

function calculateTable () {
  columns = {};
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

  if (columns[field.dataset.name]) {
    total = columns[field.dataset.name];
  } else {
    var tr = field.parentNode.parentNode.parentNode.getElementsByClassName('main-grid-row-body');
    for (var n in tr) {
      var td = tr[n].children;
      if (td && td[number]) {
        var parse = td[number].innerText.match(/^([$€]?)([\d\,\s\.]+)\s?([A-ZА-Яa-zа-я\.]*?)$/i);
        if (parse) {
          price = parseFloat(parse[2].replace(/[\,\s]+/g, ''));
          unit = parse[1] || parse[3];
          if (price && courses) {
            var course
            if (unit === '$') course = courses['USD/KZT'];
            else if (unit === '€') course = courses['EUR/KZT'];
            else if (unit === 'руб.') course = courses['RUB/KZT'];
            else if (!unit &&  parse[2].match(/\.\d{2}$/)) course = courses['GBP/KZT'];
            if(course) price = price * course;
          }
          total += price;
        }
      }
    }
    columns[field.dataset.name] = total;
  }

  field.title = field.dataset.total = total.toFixed(2).toString().replace(/(\d{1,3}(?=(\d{3})+(?:\.\d|\b)))/g,"\$1,") + ' тг.';
}

function loadCourses () {
  var courseData = sessionStorage.getItem('courses');
  if (!courseData) {
    var XHR = ('onload' in new XMLHttpRequest()) ? XMLHttpRequest : XDomainRequest;
    var xhr = new XHR();
    xhr.open('GET', 'https://backend.halykbank.kz/common/currency-history', true);
    xhr.onload = function () {
      courseData = this.responseText;
      parseCourseData(courseData);
      sessionStorage.setItem('courses', courseData);
    };
    xhr.send();
  } else {
    parseCourseData(courseData);
  }
}

function parseCourseData (data) {
  courses = JSON.parse(data).data.currencyHistory[0].legalPersons;
  for (var i in courses) courses[i] = courses[i].sell;
  calculateTable();
}

function addStyles () {
  var styles = '';
  styles += '[data-total]:after{content: attr(data-total);font-size: 11px;position: absolute;bottom: 0;left: -11px;width: 100%;text-align: center;color: lightslategrey;}';
  document.head.insertAdjacentHTML('beforeend', '<style>' + styles + '</style>');
}