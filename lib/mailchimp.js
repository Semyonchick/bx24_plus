(function () {
    // Vars
    var button = document.createElement('div'),
        menu = document.querySelector('.pagetitle-wrap .crm-entity-actions-container'),
        popup;

    if (menu && menu.querySelector('.crm-contact-menu-mail-icon')) {
        // Готовим кнопку
        button.id = 'mailchimp_list';
        button.classList.add('webform-small-button');
        button.classList.add('webform-small-button-transparent');
        button.innerText = '  ';
        button.style.background = "url('https://i0.wp.com/www.snowsbest.com/wp-content/uploads/2015/04/MailChimp-Logo.png?fit=258%2C258&ssl=1') center / 80% no-repeat";
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
            popup = BX.PopupWindowManager.create('mailchimp', this, {
                content: block,
                autoHide: true
            });
            popup.show();

            BX.ajax({
                url: 'https://smartsam.ru/bx24_plus/ajax/mailchimp.php',
                method: 'post',
                data: {
                    type: 'list',
                    email: document.querySelector('.crm-entity-email').innerText
                },
                dataType: 'json',
                onsuccess: function (data) {
                    data.result.forEach(function (row) {
                        var item = document.createElement('span');
                        item.classList.add('menu-popup-item');
                        item.classList.add('menu-popup-no-icon');
                        item.innerHTML = '<span class="menu-popup-item-text">' + row.name + '</span>';
                        menuWrapper.appendChild(item);
                        if (row.exist) {
                            item.firstElementChild.style.color = 'red';
                            item.style.cursor = 'default';
                        }
                        else
                            item.addEventListener('click', function () {
                                var data = {
                                    type: row.exist ? 'unsubscribed' : 'subscribed',
                                    listId: row.id,
                                    email: document.querySelector('.crm-entity-email').innerText,
                                    fields: {}
                                };

                                var map = {FNAME: 'NAME', LNAME: 'LAST_NAME'};
                                for (var i in map) {
                                    var el = document.querySelector('[data-cid="' + map[i] + '"] .crm-entity-widget-content-block-inner');
                                    if (el) {
                                        data.fields[i] = el.innerText;
                                    }
                                }
                                data.params = {
                                    ID: location.pathname.match(/\/(\d+)\/$/)[1],
                                    TYPE: location.pathname.match(/\/crm\/(\w+)\//)[1],
                                    LIST: row.name
                                };

                                BX.ajax({
                                    url: 'https://smartsam.ru/bx24_plus/ajax/mailchimp.php',
                                    method: 'post',
                                    data: data,
                                    dataType: 'json',
                                    onsuccess: function (data) {
                                        item.firstElementChild.style.color = 'green';
                                        item.style.cursor = 'default';
                                    }
                                });
                            });
                    });
                }
            });
        } else {
            popup.show();
        }
    });
})();