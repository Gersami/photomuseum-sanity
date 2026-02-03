import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'theme',
  title: 'Theme / Subject',
  type: 'document',

  fields: [
    defineField({
      name: 'title',
      title: 'Title',
      type: 'localizedString',
      description: 'Bilingual theme title (EN/KA). At least one is required.',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (!en && !ka) return 'Title is required in at least one language (EN or KA).'
          if (en && (en.length < 2 || en.length > 120)) return 'English title must be 2–120 characters.'
          if (ka && (ka.length < 2 || ka.length > 120)) return 'Georgian title must be 2–120 characters.'
          return true
        }),
    }),

    defineField({
      name: 'slug',
      title: 'Slug',
      type: 'slug',
      options: {
        source: (doc: any) => doc?.title?.en || doc?.title?.ka || '',
        maxLength: 96,
      },
      validation: (Rule) => Rule.required(),
    }),

    defineField({
      name: 'description',
      title: 'Public description',
      type: 'localizedText',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 2000) return 'English description must be 2000 characters or fewer.'
          if (ka.length > 2000) return 'Georgian description must be 2000 characters or fewer.'
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

    defineField({
      name: 'parent',
      title: 'Parent theme',
      type: 'reference',
      to: [{type: 'theme'}],
      description: 'Optional: use to create a hierarchy (e.g., People → Portraits).',
    }),

    defineField({
      name: 'synonyms',
      title: 'Synonyms / alternate labels',
      type: 'array',
      of: [{type: 'string'}],
      options: {layout: 'tags'},
      validation: (Rule) => Rule.max(30),
    }),

    defineField({
      name: 'coverImage',
      title: 'Cover image (optional)',
      type: 'image',
      options: {hotspot: true},
      description: 'Optional representative image for theme cards. If not set, photos from this theme will be used.',
      fields: [
        defineField({
          name: 'alt',
          title: 'Alt text',
          type: 'localizedString',
          description: 'Bilingual alt text for accessibility.',
          validation: (Rule) =>
            Rule.custom((value: any) => {
              const en = (value?.en || '').trim()
              const ka = (value?.ka || '').trim()
              if (en.length > 180) return 'English alt text must be 180 characters or fewer.'
              if (ka.length > 180) return 'Georgian alt text must be 180 characters or fewer.'
              return true
            }),
        }),
      ],
    }),
  ],

  preview: {
    select: {
      titleEn: 'title.en',
      titleKa: 'title.ka',
      parentTitleEn: 'parent.title.en',
      parentTitleKa: 'parent.title.ka',
      media: 'coverImage',
    },
    prepare({titleEn, titleKa, parentTitleEn, parentTitleKa, media}) {
      const title = titleEn || titleKa || 'Untitled theme'
      const parentTitle = parentTitleEn || parentTitleKa
      return {
        title,
        subtitle: parentTitle ? `Parent: ${parentTitle}` : undefined,
        media,
      }
    },
  },
})
