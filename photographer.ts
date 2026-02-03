// schemaTypes/photographer.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'photographer',
  title: 'Photographer',
  type: 'document',

  fields: [
    defineField({
      name: 'name',
      title: 'Name',
      type: 'localizedString',
      description: 'Bilingual name. At least one language is required.',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (!en && !ka) return 'Name is required in at least one language (EN or KA).'
          if (en && (en.length < 2 || en.length > 120)) return 'English name must be 2–120 characters.'
          if (ka && (ka.length < 2 || ka.length > 120)) return 'Georgian name must be 2–120 characters.'
          return true
        }),
    }),

    defineField({
      name: 'isUnknown',
      title: 'Unknown photographer record',
      type: 'boolean',
      initialValue: false,
      description: 'Mark true only for the single "Unknown Photographer" entry.',
    }),

    defineField({
      name: 'birthYear',
      title: 'Birth year',
      type: 'number',
      validation: (Rule) => Rule.integer().min(1700).max(2100),
    }),

    defineField({
      name: 'deathYear',
      title: 'Death year',
      type: 'number',
      validation: (Rule) =>
        Rule.integer()
          .min(1700)
          .max(2100)
          .custom((deathYear, context) => {
            const birthYear = (context.parent as any)?.birthYear
            if (typeof birthYear === 'number' && typeof deathYear === 'number' && deathYear < birthYear) {
              return 'Death year cannot be earlier than birth year.'
            }
            return true
          }),
    }),

    defineField({
      name: 'bio',
      title: 'Short bio',
      type: 'localizedText',
      description: 'Bilingual short biography (public-facing).',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 2000) return 'English bio must be 2000 characters or fewer.'
          if (ka.length > 2000) return 'Georgian bio must be 2000 characters or fewer.'
          return true
        }),
    }),

    defineField({
      name: 'notes',
      title: 'Internal notes',
      type: 'text',
      rows: 4,
      validation: (Rule) => Rule.max(2000),
    }),
  ],

  preview: {
    select: {
      nameEn: 'name.en',
      nameKa: 'name.ka',
      isUnknown: 'isUnknown',
    },
    prepare({nameEn, nameKa, isUnknown}) {
      const title = (nameEn || nameKa || 'Unnamed photographer') as string
      return {
        title,
        subtitle: isUnknown ? 'Unknown Photographer (system record)' : undefined,
      }
    },
  },
})
