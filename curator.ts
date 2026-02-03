// schemaTypes/curator.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'curator',
  title: 'Curator / Researcher',
  type: 'document',
  description:
    'Records for curators and researchers who assembled collections or created curatorial projects.',

  fields: [
    defineField({
      name: 'name',
      title: 'Name',
      type: 'localizedString',
      description: 'Curator/researcher name (bilingual).',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (!en && !ka) return 'Name is required in at least one language (EN or KA).'
          if (en && (en.length < 2 || en.length > 120))
            return 'English name must be 2–120 characters.'
          if (ka && (ka.length < 2 || ka.length > 120))
            return 'Georgian name must be 2–120 characters.'
          return true
        }),
    }),

    defineField({
      name: 'role',
      title: 'Role / Title',
      type: 'localizedString',
      description: 'Example: "Founder & Chief Curator", "Research Associate"',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 120) return 'English role must be 120 characters or fewer.'
          if (ka.length > 120) return 'Georgian role must be 120 characters or fewer.'
          return true
        }),
    }),

    defineField({
      name: 'bio',
      title: 'Biography',
      type: 'localizedText',
      description: 'Curator biography and expertise (bilingual).',
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
      name: 'yearsActive',
      title: 'Years active (note)',
      type: 'string',
      description: 'Example: "1990–2023", "1990–present"',
      validation: (Rule) => Rule.max(60),
    }),

    defineField({
      name: 'photo',
      title: 'Photo (optional)',
      type: 'image',
      options: {hotspot: true},
      description: 'Curator portrait photo.',
      fields: [
        defineField({
          name: 'alt',
          title: 'Alt text',
          type: 'string',
          description: 'Alt text for accessibility.',
        }),
      ],
    }),

    defineField({
      name: 'isFounder',
      title: 'Museum founder',
      type: 'boolean',
      initialValue: false,
      description: 'Mark true for museum founder(s).',
    }),

    defineField({
      name: 'notes',
      title: 'Internal notes',
      type: 'text',
      rows: 3,
      validation: (Rule) => Rule.max(1000),
    }),
  ],

  preview: {
    select: {
      nameEn: 'name.en',
      nameKa: 'name.ka',
      roleEn: 'role.en',
      roleKa: 'role.ka',
      isFounder: 'isFounder',
      media: 'photo',
    },
    prepare({nameEn, nameKa, roleEn, roleKa, isFounder, media}) {
      const name = nameEn || nameKa || 'Unnamed curator'
      const role = roleEn || roleKa || ''
      const founderBadge = isFounder ? '⭐ ' : ''
      return {
        title: `${founderBadge}${name}`,
        subtitle: role,
        media,
      }
    },
  },
})
