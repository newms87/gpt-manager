/* eslint-env node */
require("@rushstack/eslint-patch/modern-module-resolution");

module.exports = {
    root: true,

    parser: "vue-eslint-parser",
    parserOptions: {
        ecmaVersion: "latest",
        sourceType: "module",
        parser: "@typescript-eslint/parser",
        project: "./tsconfig.json", // Specify it only for TypeScript files
        extraFileExtensions: [".vue"]
    },

    env: {
        node: true,
        browser: true,
        "vue/setup-compiler-macros": true
    },

    extends: [
        "eslint:recommended",
        "plugin:vue/vue3-recommended", // Priority C: Recommended
        "plugin:@typescript-eslint/recommended",
        "@vue/eslint-config-typescript",
        "@vue/eslint-config-prettier/skip-formatting"
    ],

    plugins: [
        "vue",
        "@typescript-eslint",
        "import"
    ],

    globals: {
        ga: "readonly", // Google Analytics
        cordova: "readonly",
        __statics: "readonly",
        __QUASAR_SSR__: "readonly",
        __QUASAR_SSR_SERVER__: "readonly",
        __QUASAR_SSR_CLIENT__: "readonly",
        __QUASAR_SSR_PWA__: "readonly",
        process: "readonly",
        Capacitor: "readonly",
        chrome: "readonly"
    },

    rules: {
        "prefer-promise-reject-errors": "off",
        "no-debugger":
            process.env.NODE_ENV === "production" ? "error" : "off",
        "import/extensions": ["error", "never"]
    }
};
