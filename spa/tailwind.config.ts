/** @type {import("tailwindcss").Config} */
export const colors = {};

export default {
    content: [
        "./src/**/*.{html,js,vue,ts}",
        "../../quasar-ui-danx/ui/src/**/*.{html,js,vue,ts}"
    ],
    theme: {
        extend: {
            colors
        }
    },
    plugins: []
};

