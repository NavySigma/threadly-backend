const jsonHeaders = { Accept: "application/json" };

Cypress.Commands.overwrite("request", (original, arg) => {
    if (typeof arg === "string") {
        return original({ url: arg, headers: jsonHeaders });
    }
    if (typeof arg === "object") {
        arg.headers = { ...jsonHeaders, ...(arg.headers || {}) };
        return original(arg);
    }
    return original(arg);
});

Cypress.Commands.add("apiLogin", (email, password) => {
    // key = bagian sebelum @ (poster@test.com -> poster)
    const key = email.split("@")[0];
    return cy
        .request({
            method: "POST",
            url: "/login",
            body: { email, password },
            headers: jsonHeaders,
        })
        .then((res) => {
            Cypress.env(`${key}_token`, res.body.access_token);
            Cypress.env(`${key}_user`, res.body.user);
            return res.body;
        });
});

Cypress.Commands.add("apiGet", (url, username, options = {}) => {
    const auth = username
        ? { Authorization: `Bearer ${Cypress.env(`${username}_token`)}` }
        : {};
    const headers = { ...jsonHeaders, ...auth, ...(options.headers || {}) };
    const opts = { ...options, headers };
    return cy.request({ method: "GET", url, ...opts });
});

Cypress.Commands.add("apiPost", (url, body, username, options = {}) => {
    const auth = username
        ? { Authorization: `Bearer ${Cypress.env(`${username}_token`)}` }
        : {};
    const headers = { ...jsonHeaders, ...auth, ...(options.headers || {}) };
    const opts = { ...options, headers };
    return cy.request({ method: "POST", url, body, ...opts });
});

Cypress.Commands.add("apiPut", (url, body, username, options = {}) => {
    const auth = username
        ? { Authorization: `Bearer ${Cypress.env(`${username}_token`)}` }
        : {};
    const headers = { ...jsonHeaders, ...auth, ...(options.headers || {}) };
    const opts = { ...options, headers };
    return cy.request({ method: "PUT", url, body, ...opts });
});

Cypress.Commands.add("apiPatch", (url, body, username, options = {}) => {
    const auth = username
        ? { Authorization: `Bearer ${Cypress.env(`${username}_token`)}` }
        : {};
    const headers = { ...jsonHeaders, ...auth, ...(options.headers || {}) };
    const opts = { ...options, headers };
    return cy.request({ method: "PATCH", url, body, ...opts });
});

Cypress.Commands.add("apiDelete", (url, username, options = {}) => {
    const auth = username
        ? { Authorization: `Bearer ${Cypress.env(`${username}_token`)}` }
        : {};
    const headers = { ...jsonHeaders, ...auth, ...(options.headers || {}) };
    const opts = { ...options, headers };
    return cy.request({ method: "DELETE", url, ...opts });
});
