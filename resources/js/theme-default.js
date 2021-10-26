function hashChange() {
    const currentItems = document.querySelectorAll('.tocify-subheader');
    Array.from(currentItems).forEach((elem) => {
        elem.classList.remove('visible');
    });

    const currentTag = document.querySelector(`a[href="${window.location.hash}"]`);
    if (currentTag) {
        if (currentTag.parentElement.matches('.level-2')) {
            const parent = currentTag.closest('.tocify-subheader');
            if (parent) {
                parent.classList.add('visible');
            }
        }
    }
}

window.addEventListener('hashchange', hashChange, false);

document.addEventListener('DOMContentLoaded', function() {
    window.hljs.highlightAll();
    // https://jets.js.org/
    const wrapper = document.getElementById('toc');
    window.jets = new window.Jets({
        // *OR - Selects elements whose values contains at least one part of search substring
        searchSelector: '*OR',
        searchTag: '#input-search',
        contentTag: '#toc li',
        didSearch: function(term) {
            wrapper.classList.toggle('jets-searching', String(term).length > 0)
        },
        // map these accent keys to plain values
        diacriticsMap: {
            a: 'ÀÁÂÃÄÅàáâãäåĀāąĄ',
            c: 'ÇçćĆčČ',
            d: 'đĐďĎ',
            e: 'ÈÉÊËèéêëěĚĒēęĘ',
            i: 'ÌÍÎÏìíîïĪī',
            l: 'łŁ',
            n: 'ÑñňŇńŃ',
            o: 'ÒÓÔÕÕÖØòóôõöøŌō',
            r: 'řŘ',
            s: 'ŠšśŚ',
            t: 'ťŤ',
            u: 'ÙÚÛÜùúûüůŮŪū',
            y: 'ŸÿýÝ',
            z: 'ŽžżŻźŹ'
        }
    });

    hashChange();
});
