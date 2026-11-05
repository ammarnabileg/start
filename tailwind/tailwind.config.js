// إعدادات Tailwind — بتتبني مرة واحدة لملف assets/app.css (بدل cdn.tailwindcss.com)
const v = (n) => Object.fromEntries([50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((k) => [k, `rgb(var(--${n}-${k}) / <alpha-value>)`]));
module.exports = {
  darkMode: 'class',
  content: ['./app/**/*.php', './assets/**/*.js', './*.php', './tailwind/safelist.txt'],
  theme: {
    extend: {
      fontFamily: { sans: ['"Twemoji Country Flags"', 'Alexandria', 'system-ui', 'sans-serif'] },
      colors: { white: 'rgb(var(--surface) / <alpha-value>)', ink: v('ink'), brand: v('brand'), cyan: v('cyan'), lime: v('lime'), red: v('red'), amber: v('amber'), slate: v('ink'), gray: v('ink'), indigo: v('brand'), blue: v('brand'), emerald: v('lime'), green: v('lime'), rose: v('red'), orange: v('amber') },
    },
  },
  plugins: [],
};
