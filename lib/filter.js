(function () {
  if (location.hash) {
    var parse = location.hash.match(/^#\/f\/(.+)\//)
    if (parse) {
      var params = {}
      var rows = []
      parse[1].split('/').forEach(function (row) {
        var key = '', tmpKey = ''
        row.split(/-(from|to|is|or)-/).forEach(function (row) {
          if (!key) {
            key = row
            rows.push(key)
          } else if (row === 'is' || row === 'or') {
            return false
          } else if (row === 'from' || row === 'to') {
            params[key + '_datesel'] = 'RANGE'
            tmpKey = key + '_' + row
          } else if (tmpKey) {
            params[tmpKey] = row
            tmpKey = false
          } else {
            if (!params[key]) params[key] = []
            params[key].push(decodeURI(row))
          }
        })
      })

      if (Object.values(params).length) {
        var linkParams = location.pathname.split('/')
        var linkType = linkParams[1].toUpperCase() + '_' + linkParams[2].toUpperCase() + '_LIST'
        document.body.innerHTML = ''
        window.stop()
        BX.ajax({
          url: 'https://holding-gel.bitrix24.ru/bitrix/components/bitrix/main.ui.filter/settings.ajax.php?FILTER_ID=' + linkType + '_V12&GRID_ID=' + linkType + '_V12&action=SET_FILTER',
          method: 'post',
          data: {
            apply_filter: 'Y',
            save: 'Y',
            preset_id: 'tmp_filter',
            fields: params,
            rows: rows
          },
          dataType: 'json',
          onsuccess: function (data) {
            location.href = location.pathname
          }
        })
      }
    }
  }
})()