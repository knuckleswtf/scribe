document.addEventListener('DOMContentLoaded', function() {
    const updateHash = function (id) {
        window.location.hash = `#${id}`;
    };

    const throttledUpdateHash = _.throttle(updateHash, 200);

    const observer = new IntersectionObserver((entries) => {
        // If intersectionRatio is 0, the target is out of view
        // and we do not need to do anything.
        if (entries[0].intersectionRatio <= 0) {
            return;
        }

        throttledUpdateHash(entries[0].target.id);
    }, {
        rootMargin: '-8% 0px -8% 0px', // shrink the intersection viewport
        threshold: 1.0, // trigger at 100% visibility
    });

    function makeObserver(elem) {
        return observer.observe(elem);
    }

    const titles = document.querySelectorAll('.content h1, .content h2');
    Array.from(titles).forEach(makeObserver);

    const navButton = document.getElementById('nav-button');
    const menuWrapper = document.querySelector('.tocify-wrapper');
    function toggleSidebar() {
        if (menuWrapper) {
            menuWrapper.classList.toggle('open');
            navButton.classList.toggle('open');
        }
    }
    function closeSidebar() {
        if (menuWrapper) {
            menuWrapper.classList.remove('open');
            navButton.classList.remove('open');
        }
    }
    navButton.addEventListener('click', toggleSidebar);

    window.hljs.highlightAll();

    const wrapper = document.getElementById('toc');
    // https://jets.js.org/
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

    function hashChange() {
        const currentItems = document.querySelectorAll('.tocify-subheader.visible, .tocify-item.tocify-focus');
        Array.from(currentItems).forEach((elem) => {
            elem.classList.remove('visible', 'tocify-focus');
        });

        const currentTag = document.querySelector(`a[href="${window.location.hash}"]`);
        if (currentTag) {
            const parent = currentTag.closest('.tocify-subheader');
            if (parent) {
                parent.classList.add('visible');
            }

            const siblings = currentTag.closest('.tocify-header');
            if (siblings) {
                Array.from(siblings.querySelectorAll('.tocify-subheader')).forEach((elem) => {
                    elem.classList.add('visible');
                });
            }

            currentTag.parentElement.classList.add('tocify-focus');

            // wait for dom changes to be done
            setTimeout(() => {
                currentTag.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                // only close the sidebar on level-2 events
                if (currentTag.parentElement.classList.contains('level-2')) {
                    closeSidebar();
                }
            }, 1500);
        }
    }

    window.addEventListener('hashchange', hashChange, false);

    hashChange();
});
