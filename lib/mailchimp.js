define(function () {
    // Vars
    var button = document.createElement('div'),
        menu = document.querySelector('.pagetitle-wrap .crm-entity-actions-container'),
        menuWrapper = document.createElement('div'),
        popup;

    // Готовим кнопку
    button.id = 'mailchimp_list';
    button.classList.add('webform-small-button');
    button.classList.add('webform-small-button-transparent');
    button.innerText = '  ';
    button.style.background = "url('https://i0.wp.com/www.snowsbest.com/wp-content/uploads/2015/04/MailChimp-Logo.png?fit=258%2C258&ssl=1') center / 90% no-repeat";

    menuWrapper.classList.add('menu-popup-items');
    // Ставим кнопку
    if (menu && menu.querySelector('.crm-contact-menu-mail-icon'))
        menu.insertBefore(button, menu.firstChild);

    button.addEventListener('click', function () {
        if (!popup) {
            BX.ajax({
                url: 'https://smartsam.ru/bx24_plus/ajax/mailchimp.php',
                data: {type: 'list'}.type,
                dataType: 'json',
                onsuccess: function (data) {
                    data.result.forEach(function (row) {
                        var item = document.createElement('span');
                        item.classList.add('menu-popup-item');
                        item.classList.add('menu-popup-no-icon');
                        item.innerHTML = '<span class="menu-popup-item-text">' + row.name + '</span>';
                        menuWrapper.appendChild(item);
                    });
                    popup = BX.PopupWindowManager.create('mailchimp', document.getElementById('mailchimp_list'), {
                        content: '<div class="menu-popup" style="display:block;margin:-10px">' + menuWrapper.outerHTML + '</div>',
                        autoHide: true
                    });
                    popup.show();
                }
            });
        } else {
            popup.show();
        }


    })

});
