// window.addEventListener('load', function() {
document.addEventListener('DOMContentLoaded', function() {
    // const exampleLanguages = JSON.parse(document.body.getAttribute('data-languages'));
    window.hljs.highlightAll();
    // https://jets.js.org/
    window.jets = new window.Jets({
        searchTag: '#input-search',
        contentTag: '#toc li'
    });
});
