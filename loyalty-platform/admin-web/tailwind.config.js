module.exports = {
  content: ['./*.php','./partials/*.php','./lib/*.php'],
  safelist: [{ pattern: /(bg|text|border)-(amber|blue|green|red|gray|emerald|slate)-(50|100|200|300|400|500|600|700|800|900)/ }],
  theme: { extend: { colors: { brand: '#F4B400' } } },
};
