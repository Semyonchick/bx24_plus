if (location.host === 'espanarusa.bitrix24.ru') {
    requirejs.config({
        baseUrl: 'https://smartsam.ru/bx24_plus/lib',
        urlArgs: "v=20171206-1625"
    });

    requirejs(['mailchimp', 'hyperscript', 'lists_sum']);

    // console.log('bx24_plus init');
} else if (location.host === 'holding-gel.bitrix24.ru') {
    requirejs.config({
        baseUrl: 'https://smartsam.ru/bx24_plus/lib',
        urlArgs: "v=20180222-1554"
    });

    requirejs(['filter']);

    console.log('bx24_plus init');
} else if (location.host === 'asiatechno.bitrix24.kz') {
    requirejs.config({
        baseUrl: 'https://smartsam.ru/bx24_plus/lib',
        urlArgs: "v=20180222-1554"
    });

    requirejs(['lists_sum_v2']);

    console.log('bx24_plus init');
}
