import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  theme: {
    extend: {
      colors: {
        cream: '#F9F4EF',
        brand: {
          navy: '#2D3150',
          gold: '#F1CE33',
          brown: {
            DEFAULT: '#6B5744',
            dark: '#453428',
          },
          tan: '#AD906B',
          sage: '#B3C3A1',
          terracotta: '#AC7F5E',
          footer: '#4A4038',
        },
      },
      fontFamily: {
        display: ['"Baloo 2"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/typography')],
}
