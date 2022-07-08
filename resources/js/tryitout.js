window.abortControllers = {};

function cacheAuthValue() {
    // Whenever the auth header is set for one endpoint, cache it for the others
    window.lastAuthValue = '';
    document.querySelectorAll(`label[id^=auth-] > input`)
        .forEach(el => {
            el.addEventListener('change', (event) => {
                window.lastAuthValue = event.target.value;
                document.querySelectorAll(`label[id^=auth-] > input`)
                    .forEach(otherInput => {
                        if (otherInput === el) return;
                        // Don't block the main thread
                       setTimeout(() => {
                           otherInput.value = window.lastAuthValue;
                        }, 0);
                    });
            });
        });
}

window.addEventListener('DOMContentLoaded', cacheAuthValue);

function getCookie(name) {
    if (!document.cookie) {
        return null;
    }

    const cookies = document.cookie.split(';')
        .map(c => c.trim())
        .filter(c => c.startsWith(name + '='));

    if (cookies.length === 0) {
        return null;
    }

    return decodeURIComponent(cookies[0].split('=')[1]);
}

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
    if (window.abortControllers[endpointId]) {
        window.abortControllers[endpointId].abort();
        delete window.abortControllers[endpointId];
    }

    document.querySelector(`#btn-tryout-${endpointId}`).hidden = false;
    const executeBtn = document.querySelector(`#btn-executetryout-${endpointId}`);
    executeBtn.hidden = true;
    executeBtn.textContent = "Send Request 💥";
    document.querySelector(`#btn-canceltryout-${endpointId}`).hidden = true;
    // Hide inputs
    document.querySelectorAll(`input[data-endpoint=${endpointId}],label[data-endpoint=${endpointId}]`)
        .forEach(el => el.hidden = true);
    document.querySelectorAll(`#form-${endpointId} details`)
        .forEach(el => el.open = false);
    const authElement = document.querySelector(`#auth-${endpointId}`);
    authElement && (authElement.hidden = true);

    document.querySelector('#execution-results-' + endpointId).hidden = true;
    document.querySelector('#execution-error-' + endpointId).hidden = true;

    // Revert to sample code blocks
    document.querySelector('#example-requests-' + endpointId).hidden = false;
    document.querySelector('#example-responses-' + endpointId).hidden = false;
}

function makeAPICall(method, path, body, query, headers, endpointId) {
    console.log({endpointId, path, body, query, headers});

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

    window.abortControllers[endpointId] = new AbortController();

    return fetch(url, {
        method,
        headers,
        body: method === 'GET' ? undefined : body,
        signal: window.abortControllers[endpointId].signal,
        referrer: window.baseUrl,
        mode: 'cors',
        credentials: 'same-origin',
    })
        .then(response => Promise.all([response.status, response.text(), response.headers]));
}

function hideCodeSamples(endpointId) {
    document.querySelector('#example-requests-' + endpointId).hidden = true;
    document.querySelector('#example-responses-' + endpointId).hidden = true;
}

function handleResponse(endpointId, response, status, headers) {
    hideCodeSamples(endpointId);

    // Hide error views
    document.querySelector('#execution-error-' + endpointId).hidden = true;

    const responseContentEl = document.querySelector('#execution-response-content-' + endpointId);

    // Prettify it if it's JSON
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

function handleError(endpointId, err) {
    hideCodeSamples(endpointId);
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
    if (form.dataset.hasfiles === "1") {
        body = new FormData();
        setter = (name, value) => body.append(name, value);
    } else if (form.dataset.isarraybody === "1") {
        body = [];
        setter = (name, value) => _.set(body, name, value);
    } else {
        body = {};
        setter = (name, value) => _.set(body, name, value);
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
    queryParameters.forEach(el => {
        if (el.type !== 'radio' || (el.type === 'radio' && el.checked)) {
            if (el.value === '' && el.required === false) {
                // Don't include empty optional values in the request
                return;
            }

            _.set(query, el.name, el.value);
        }
    });

    let path = form.dataset.path;
    const urlParameters = form.querySelectorAll('input[data-component=url]');
    urlParameters.forEach(el => (path = path.replace(new RegExp(`\\{${el.name}\\??}`), el.value)));

    const headers = JSON.parse(form.dataset.headers);
    // Check for auth param that might go in header
    if (form.dataset.authed === "1") {
        const authHeaderEl = form.querySelector('input[data-component=header]');
        if (authHeaderEl) headers[authHeaderEl.name] = authHeaderEl.dataset.prefix + authHeaderEl.value;
    }
    // When using FormData, the browser sets the correct content-type + boundary
    let method = form.dataset.method;
    if (body instanceof FormData) {
        delete headers['Content-Type'];

        // When using FormData with PUT or PATCH, use method spoofing so PHP can access the post body
        if (['PUT', 'PATCH'].includes(form.dataset.method)) {
            method = 'POST';
            setter('_method', form.dataset.method);
        }
    }

    let preflightPromise = Promise.resolve();
    if (window.useCsrf && window.csrfUrl) {
        preflightPromise = makeAPICall('GET', window.csrfUrl, {}, {}, {}, null).then(() => {
            headers['X-XSRF-TOKEN'] = getCookie('XSRF-TOKEN');
        });
    }

    return preflightPromise.then(() => makeAPICall(method, path, body, query, headers, endpointId))
        .then(([responseStatus, responseContent, responseHeaders]) => {
            handleResponse(endpointId, responseContent, responseStatus, responseHeaders)
        })
        .catch(err => {
            if (err.name === "AbortError") {
                console.log("Request cancelled");
                return;
            }
            console.log("Error while making request: ", err);
            handleError(endpointId, err);
        })
        .finally(() => {
            executeBtn.textContent = "Send Request 💥";
        });
}
