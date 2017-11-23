define(function () {
    // Vars
    var button = document.createElement('div'),
        menu = document.querySelector('.pagetitle-wrap .crm-entity-actions-container'),
        popup;

    if (menu && menu.querySelector('.crm-contact-menu-call-icon')) {
        // Готовим кнопку
        button.id = 'hyperscript_list';
        button.classList.add('webform-small-button');
        button.classList.add('webform-small-button-transparent');
        button.innerText = '  ';
        button.style.background = "rgba(70, 93, 106, 0.7) url('https://hyper-script.ru/themes/hyperscript/img/logo_s.png') center no-repeat";
        button.style.width = "42px";
        button.style.marginRight = "6px";
        // Ставим кнопку
        menu.insertBefore(button, menu.firstChild);
    }

    button.addEventListener('click', function () {
        if (!popup) {
            var block = document.createElement('div'), menuWrapper = document.createElement('div');
            block.classList.add('menu-popup');
            block.style.display = 'block';
            block.style.margin = '-10px';
            block.appendChild(menuWrapper);
            menuWrapper.classList.add('menu-popup-items');
            popup = BX.PopupWindowManager.create('hyperscript', this, {
                content: block,
                autoHide: true
            });
            popup.show();


            try {
                var data = localStorage.getItem('hyperScriptMenu');
                data = JSON.parse(data);
                if (data.length) data.forEach(addMenu);
            } catch (e) {
            }

            BX.ajax({
                url: 'https://hyper-script.ru/api/bitrix/get_scripts',
                method: 'post',
                data: {user: 'semyonchick@gmail.com'},
                dataType: 'json',
                onsuccess: function (data) {
                    if (data.response.scripts) {
                        menuWrapper.innerHTML = '';
                        data.response.scripts.forEach(addMenu);
                        localStorage.setItem('hyperScriptMenu', JSON.stringify(data.response.scripts));
                    } else if (!menuWrapper.innerHTML) {
                        if (window.confirm('You need auth in hyperscript. OK to do.')) {
                            window.location.href = ' https://espanarusa.bitrix24.ru/marketplace/app/7/';
                        }
                    }
                    popup = null;
                }
            });
        } else {
            popup.show();
        }

        function addMenu(row) {
            var item = document.createElement('span');
            item.classList.add('menu-popup-item');
            item.classList.add('menu-popup-no-icon');
            item.innerHTML = '<span class="menu-popup-item-text">' + row.name + '</span>';
            menuWrapper.appendChild(item);
            item.addEventListener('click', function () {
                var params = location.pathname.split('/');

                var f = document.createElement('form');
                f.action = 'https://espanarusa.bitrix24.ru/marketplace/app/7/?script=' + row.id + '&CRM_ENTITY_TYPE=' + params[2].toUpperCase() + '&CRM_ENTITY_ID=' + params[4] + '&back=' + location.href;
                f.target = '_parent'
                document.body.appendChild(f);
                f.submit();
            });
        }
    });

});
