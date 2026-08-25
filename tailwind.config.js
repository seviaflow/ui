import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './src/**/*.php',
    './vendor/flowbite/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [flowbitePlugin],
};
