function tryItOut(endpointId) {
    document.querySelector(`#btn-tryout-${endpointId}`).hidden = true;
    document.querySelector(`#btn-executetryout-${endpointId}`).hidden = false;
    document.querySelector(`#btn-canceltryout-${endpointId}`).hidden = false;

    // Show all input fields
    document.querySelectorAll(`input[data-endpoint=${endpointId}],label[data-endpoint=${endpointId}]`)
        .forEach(el => el.hidden = false);

    if (document.querySelector(`#form-${endpointId}`).dataset.authed === "1") {
        const authElement = document.querySelector(`#auth-${endpointId}`);
        authElement && (authElement.hidden = false);
    }
    // Expand all nested fields
    document.querySelectorAll(`#form-${endpointId} details`)
        .forEach(el => el.open = true);
}

function cancelTryOut(endpointId) {
    document.querySelector(`#btn-tryout-${endpointId}`).hidden = false;
    const executeBtn = document.querySelector(`#btn-executetryout-${endpointId}`);
    executeBtn.hidden = true;
    executeBtn.textContent = "Send Request 💥";
    document.querySelector(`#btn-canceltryout-${endpointId}`).hidden = true;
    // hide inputs
    document.querySelectorAll(`input[data-endpoint=${endpointId}],label[data-endpoint=${endpointId}]`)
        .forEach(el => el.hidden = true);
    document.querySelectorAll(`#form-${endpointId} details`)
        .forEach(el => el.open = false);
    const authElement = document.querySelector(`#auth-${endpointId}`);
    authElement && (authElement.hidden = true);

    document.querySelector('#execution-results-' + endpointId).hidden = true;
    document.querySelector('#execution-error-' + endpointId).hidden = true;

    // Revert to sample code blocks
    const query = new URLSearchParams(window.location.search);
    const languages = JSON.parse(document.querySelector('body').dataset.languages);
    const currentLanguage = languages.find(l => query.has(l)) || languages[0];

    let codeblock = getPreviousSiblingUntil(document.querySelector('#form-' + endpointId), 'blockquote,pre', 'h2');
    while (codeblock != null) {
        if (codeblock.nodeName === 'PRE') {
            if (codeblock.querySelector('code.language-' + currentLanguage)) {
                codeblock.style.display = 'block';
            }
        } else {
            codeblock.style.display = 'block';
        }
        codeblock = getPreviousSiblingUntil(codeblock, 'blockquote,pre', 'h2');
    }
}

function makeAPICall(method, path, body, query, headers) {
    console.log({path, body, query, headers});

    if (!(body instanceof FormData)) {
        body = JSON.stringify(body)
    }

    const url = new URL(window.baseUrl + '/' + path.replace(/^\//, ''));

    // We need this function because if you try to set an array or object directly to a URLSearchParams object,
    // you'll get [object Object] or the array.toString()
    function addItemToSearchParamsObject(key, value, searchParams) {
            if (Array.isArray(value)) {
                value.forEach((v, i) => {
                    // Append {filters: [first, second]} as filters[0]=first&filters[1]second
                    addItemToSearchParamsObject(key + '[' + i + ']', v, searchParams);
                })
            } else if (typeof value === 'object' && value !== null) {
                Object.keys(value).forEach((i) => {
                    // Append {filters: {name: first}} as filters[name]=first
                    addItemToSearchParamsObject(key + '[' + i + ']', value[i], searchParams);
                });
            } else {
                searchParams.append(key, value);
            }
    }

    Object.keys(query)
        .forEach(key => addItemToSearchParamsObject(key, query[key], url.searchParams));

    return fetch(url, {
        method,
        headers,
        body: method === 'GET' ? undefined : body,
    })
        .then(response => Promise.all([response.status, response.text(), response.headers]));
}

function hideCodeSamples(form) {
    let codeblock = getPreviousSiblingUntil(form, 'blockquote,pre', 'h2');
    while (codeblock != null) {
        codeblock.style.display = 'none';
        codeblock = getPreviousSiblingUntil(codeblock, 'blockquote,pre', 'h2');
    }
}

function handleResponse(form, endpointId, response, status, headers) {
    hideCodeSamples(form);

    // Hide error views
    document.querySelector('#execution-error-' + endpointId).hidden = true;


    const responseContentEl = document.querySelector('#execution-response-content-' + endpointId);

    // prettify it if it's JSON
    let isJson = false;
    try {
        const jsonParsed = JSON.parse(response);
        if (jsonParsed !== null) {
            isJson = true;
            response = JSON.stringify(jsonParsed, null, 4);
        }
    } catch (e) {

    }
    responseContentEl.textContent = response === '' ? '<Empty response>' : response;
    isJson && window.hljs.highlightBlock(responseContentEl);
    const statusEl = document.querySelector('#execution-response-status-' + endpointId);
    statusEl.textContent = ` (${status})`;
    document.querySelector('#execution-results-' + endpointId).hidden = false;
    statusEl.scrollIntoView({behavior: "smooth", block: "center"});
}

function handleError(form, endpointId, err) {
    hideCodeSamples(form);
    // Hide response views
    document.querySelector('#execution-results-' + endpointId).hidden = true;

    // Show error views
    let errorMessage = err.message || err;
    errorMessage += "\n\nTip: Check that you're properly connected to the network.";
    errorMessage += "\nIf you're a maintainer of ths API, verify that your API is running and you've enabled CORS.";
    errorMessage += "\nYou can check the Dev Tools console for debugging information.";
    document.querySelector('#execution-error-message-' + endpointId).textContent = errorMessage;
    const errorEl = document.querySelector('#execution-error-' + endpointId);
    errorEl.hidden = false;
    errorEl.scrollIntoView({behavior: "smooth", block: "center"});

}

async function executeTryOut(endpointId, form) {
    const executeBtn = document.querySelector(`#btn-executetryout-${endpointId}`);
    executeBtn.textContent = "⏱ Sending...";
    executeBtn.scrollIntoView({behavior: "smooth", block: "center"});

    let body;
    let setter;
    if (form.dataset.hasfiles === "0") {
        body = {};
        setter = (name, value) => _.set(body, name, value);
    } else {
        body = new FormData();
        setter = (name, value) => body.append(name, value);
    }
    const bodyParameters = form.querySelectorAll('input[data-component=body]');
    bodyParameters.forEach(el => {
        let value = el.value;
        if (el.type === 'file' && el.files[0]) {
            setter(el.name, el.files[0]);
            return;
        }

        if (el.type !== 'radio') {
            if (value === "" && el.required === false) {
                // Don't include empty optional values in the request
                return;
            }
            setter(el.name, value);
            return;
        }

        if (el.checked) {
            value = (value === 'false') ? false : true;
            setter(el.name, value);
        }
    });

    const query = {};
    const queryParameters = form.querySelectorAll('input[data-component=query]');
    queryParameters.forEach(el => _.set(query, el.name, el.value));

    let path = form.dataset.path;
    const urlParameters = form.querySelectorAll('input[data-component=url]');
    urlParameters.forEach(el => (path = path.replace(new RegExp(`\\{${el.name}\\??}`), el.value)));

    const headers = JSON.parse(form.dataset.headers);
    // Check for auth param that might go in header
    if (form.dataset.authed === "1") {
        const authHeaderEl = form.querySelector('input[data-component=header]');
        if (authHeaderEl) headers[authHeaderEl.name] = authHeaderEl.dataset.prefix + authHeaderEl.value;
    }

    makeAPICall(form.dataset.method, path, body, query, headers)
        .then(([responseStatus, responseContent, responseHeaders]) => {
            handleResponse(form, endpointId, responseContent, responseStatus, responseHeaders)
        })
        .catch(err => {
            console.log("Error while making request: ", err);
            handleError(form, endpointId, err);
        })
        .finally(() => {
            executeBtn.textContent = "Send Request 💥";
        });
}

function getPreviousSiblingUntil(elem, siblingSelector, stopSelector) {
    let sibling = elem.previousElementSibling;
    while (sibling) {
        if (sibling.matches(siblingSelector)) return sibling;
        if (sibling.matches(stopSelector)) return null;
        sibling = sibling.previousElementSibling;
    }
}