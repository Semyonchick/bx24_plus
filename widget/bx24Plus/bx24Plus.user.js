// ==UserScript==
// @name HyperScriptPlus
// @description Система помощи для HyperScript
// @author SmartSam
// @license MIT
// @version 1.0
// @include https://hyper-script.ru/*
// @require https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.5/require.min.js
// @require https://smartsam.ru/hyper_plus/init.js
// ==/UserScript==

var script = document.createElement('script');
script.src = 'https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.5/require.min.js';
script.dataset.main = 'https://smartsam.ru/bx24_plus/init.js';
document.head.appendChild(script);