if (location.host === 'espanarusa.bitrix24.ru') {
    requirejs.config({
        baseUrl: 'https://smartsam.ru/bx24_plus/lib',
        urlArgs: "v=20171206-1625"
    });

    requirejs(['mailchimp', 'hyperscript', 'lists_sum']);

    console.log('bx24_plus init');
} else if (location.host === 'holding-gel.bitrix24.ru') {
    requirejs.config({
        baseUrl: 'https://smartsam.ru/bx24_plus/lib',
        urlArgs: "v=20171218-1039"
    });

    // requirejs(['mailchimp']);

    console.log('bx24_plus init');
}