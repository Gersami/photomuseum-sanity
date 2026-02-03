// schemaTypes/localizedText.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'localizedText',
  title: 'Localized text',
  type: 'object',
  description: 'Bilingual longer text (EN/KA) for public descriptions and essays.',

  fields: [
    defineField({
      name: 'en',
      title: 'English',
      type: 'text',          // ✅ MUST be text
      options: {rows: 6},    // ✅ now works
      validation: (Rule) => Rule.max(2000),
    }),
    defineField({
      name: 'ka',
      title: 'Georgian',
      type: 'text',          // ✅ MUST be text
      options: {rows: 6},
      validation: (Rule) => Rule.max(2000),
    }),
  ],

  preview: {
    select: {en: 'en', ka: 'ka'},
    prepare({en, ka}) {
      return {title: en || ka || '(empty)', subtitle: en && ka ? ka : undefined}
    },
  },
})
