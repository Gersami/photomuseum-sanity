// schemaTypes/localizedString.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'localizedString',
  title: 'Localized string',
  type: 'object',
  description:
    'Bilingual field (EN/KA) for public-facing text. Use schema-level or field-level validation to enforce length where needed (e.g., alt/caption).',

  fields: [
    defineField({
      name: 'en',
      title: 'English',
      type: 'string',
      validation: (Rule) => Rule.max(2000),
    }),
    defineField({
      name: 'ka',
      title: 'Georgian',
      type: 'string',
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
