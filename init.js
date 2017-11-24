requirejs.config({
    baseUrl: 'https://smartsam.ru/bx24_plus/lib',
    urlArgs: "v=201724111211"
});

requirejs(['mailchimp', 'hyperscript']);

console.log('bx24_plus init');