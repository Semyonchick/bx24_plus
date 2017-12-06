requirejs.config({
    baseUrl: 'https://smartsam.ru/bx24_plus/lib',
    urlArgs: "v=20171206-1625"
});

requirejs(['mailchimp', 'hyperscript', 'lists_sum']);

console.log('bx24_plus init');