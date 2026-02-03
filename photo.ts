// /schemaTypes/photo.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'photo',
  title: 'Photo',
  type: 'document',

  groups: [
    {name: 'core', title: 'Core'},
    {name: 'context', title: 'Context'},
    {name: 'subject', title: 'Subject'},
    {name: 'text', title: 'Text'},
    {name: 'rights', title: 'Rights & Technical'},
    {name: 'internal', title: 'Internal'},
  ],

  fields: [
    // ========== CORE GROUP ==========
    defineField({
      name: 'title',
      title: 'Title',
      type: 'localizedString',
      group: 'core',
      description: 'Public-facing title (bilingual). At least one language is required.',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (!en && !ka) return 'Title is required in at least one language (EN or KA).'
          if (en && (en.length < 3 || en.length > 120)) return 'English title must be 3–120 characters.'
          if (ka && (ka.length < 3 || ka.length > 120)) return 'Georgian title must be 3–120 characters.'
          return true
        }),
    }),

    defineField({
      name: 'slug',
      title: 'Slug',
      type: 'slug',
      group: 'core',
      options: {
        source: (doc: any) => doc?.title?.en || doc?.title?.ka || '',
        maxLength: 96,
        slugify: (input: string) =>
          input
            .toLowerCase()
            .trim()
            .replace(/[^\p{L}\p{N}]+/gu, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 96),
      },
      validation: (Rule) => Rule.required(),
    }),

    defineField({
      name: 'image',
      title: 'Image',
      type: 'image',
      group: 'core',
      options: {hotspot: true},
      validation: (Rule) => Rule.required(),
      fields: [
        defineField({
          name: 'alt',
          title: 'Alt text',
          type: 'localizedString',
          description: 'Bilingual alt text for accessibility. Keep it concise.',
          validation: (Rule) =>
            Rule.custom((value: any) => {
              const en = (value?.en || '').trim()
              const ka = (value?.ka || '').trim()
              if (en.length > 180) return 'English alt text must be 180 characters or fewer.'
              if (ka.length > 180) return 'Georgian alt text must be 180 characters or fewer.'
              return true
            }),
        }),
        defineField({
          name: 'caption',
          title: 'Caption',
          type: 'localizedString',
          description: 'Bilingual caption shown alongside the image.',
          validation: (Rule) =>
            Rule.custom((value: any) => {
              const en = (value?.en || '').trim()
              const ka = (value?.ka || '').trim()
              if (en.length > 200) return 'English caption must be 200 characters or fewer.'
              if (ka.length > 200) return 'Georgian caption must be 200 characters or fewer.'
              return true
            }),
        }),
      ],
    }),

    defineField({
      name: 'publicDescription',
      title: 'Public description',
      type: 'localizedText',
      group: 'core',
      description:
        'Primary public-facing text (bilingual). Use this like WordPress "Description" for the media item.',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 2000) return 'English public description must be 2000 characters or fewer.'
          if (ka.length > 2000) return 'Georgian public description must be 2000 characters or fewer.'
          return true
        }),
    }),

    // ========== CONTEXT GROUP (SIMPLIFIED) ==========
    defineField({
      name: 'photographerRef',
      title: 'Photographer',
      type: 'reference',
      to: [{type: 'photographer'}],
      group: 'context',
      description: 'Link to a Photographer record (optional if unknown).',
    }),

    defineField({
      name: 'collectionRef',
      title: 'Collection / Album',
      type: 'reference',
      to: [{type: 'collection'}],
      group: 'context',
      description: 'Link to a Collection / Album record (optional).',
    }),

    defineField({
      name: 'dateNote',
      title: 'Date / Period',
      type: 'localizedString',
      group: 'context',
      description:
        'Human-readable date (bilingual). Examples: exact year ("1897"), decade ("1890s"), range ("1870–1916"), approximation ("circa 1900"), or period ("late 19th century"). This displays publicly on photo page.',
      validation: (Rule) =>
        Rule.required().custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (!en && !ka) return 'Date is required in at least one language (EN or KA).'
          if (en && en.length > 120) return 'English date must be 120 characters or fewer.'
          if (ka && ka.length > 120) return 'Georgian date must be 120 characters or fewer.'
          return true
        }),
    }),

    // ========== SUBJECT GROUP ==========
    defineField({
      name: 'placeRefs',
      title: 'Places',
      type: 'array',
      group: 'subject',
      of: [{type: 'reference', to: [{type: 'place'}]}],
      description: 'Link to Place records (country/region/city/district/site etc.).',
      validation: (Rule) => Rule.unique().max(20),
    }),

    defineField({
      name: 'tagRefs',
      title: 'Tags',
      type: 'array',
      group: 'subject',
      of: [{type: 'reference', to: [{type: 'tag'}]}],
      description: 'Controlled vocabulary tags for faceted search and filtering.',
      validation: (Rule) => Rule.unique().max(30),
    }),

    defineField({
      name: 'themeRefs',
      title: 'Themes / Subjects (curated)',
      type: 'array',
      group: 'subject',
      of: [{type: 'reference', to: [{type: 'theme'}]}],
      description: 'Curated themes (not the same as Tags).',
      validation: (Rule) => Rule.unique().max(20),
    }),

    // ========== TEXT GROUP ==========
    defineField({
      name: 'archivalDescription',
      title: 'Archival description (factual)',
      type: 'text',
      group: 'text',
      rows: 6,
      description: 'Factual catalog text (what/where/who/inscriptions/observable details).',
      validation: (Rule) => Rule.max(1200),
    }),

    defineField({
      name: 'curatorialNotes',
      title: 'Curatorial notes (interpretive)',
      type: 'text',
      group: 'text',
      rows: 6,
      description:
        'Interpretation, historical context, significance, comparisons, discussion of uncertainty.',
      validation: (Rule) => Rule.max(2000),
    }),

    // ========== RIGHTS & TECHNICAL GROUP (UPDATED) ==========
    defineField({
      name: 'rightsStatus',
      title: 'Rights / Usage status',
      type: 'string',
      group: 'rights',
      validation: (Rule) => Rule.required(),
      options: {
        list: [
          {title: 'Public domain (free use)', value: 'public_domain'},
          {title: 'Museum collection (licensing available)', value: 'museum_collection'},
          {title: 'Archive holding (licensing via museum)', value: 'archive_holding'},
          {title: 'Restricted (permission required)', value: 'restricted'},
          {title: 'Rights unknown (contact museum)', value: 'unknown'},
        ],
        layout: 'radio',
      },
      initialValue: 'unknown',
      description:
        'Legal usage status. "Museum collection" = museum holds rights directly. "Archive holding" = museum brokers licensing from another institution. Displays publicly.',
    }),

    defineField({
      name: 'attribution',
      title: 'Attribution / Credit',
      type: 'localizedString',
      group: 'rights',
      description:
        'Public credit line (bilingual). Standard format: "Photographer Name / Georgian Museum of Photography" OR "Collection Name / Museum". Examples: "Dmitri Ermakov / Photomuseum", "Unknown Photographer / Photomuseum", "Grishashvili Family Collection / Photomuseum".',
      // ✅ FIX: localizedString is an object, so use custom length checks per language
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 200) return 'English attribution must be 200 characters or fewer.'
          if (ka.length > 200) return 'Georgian attribution must be 200 characters or fewer.'
          return true
        }),
    }),

    defineField({
      name: 'source',
      title: 'Source / Provenance',
      type: 'localizedString',
      group: 'rights',
      description:
        "Where this photograph came from (bilingual). Include: (1) Holding institution/collection name, (2) Original reference ID if known. Examples: \"Georgian National Archives, fond 1448\", \"J. Grishashvili family collection\", \"Father's website (photomuseum.org.ge), ref: IMG_1234\". Displays publicly for transparency.",
      // ✅ FIX: localizedString is an object, so use custom length checks per language
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 300) return 'English source must be 300 characters or fewer.'
          if (ka.length > 300) return 'Georgian source must be 300 characters or fewer.'
          return true
        }),
    }),

    // ========== INTERNAL GROUP ==========
    defineField({
      name: 'internalNotes',
      title: 'Internal notes',
      type: 'text',
      group: 'internal',
      rows: 4,
      description: 'Internal-only notes (tasks, questions, disputes, reminders). Not for public display.',
      validation: (Rule) => Rule.max(2000),
    }),
  ],

  preview: {
    select: {
      titleEn: 'title.en',
      titleKa: 'title.ka',
      media: 'image',
      dateNote: 'dateNote.en',
      photographerNameEn: 'photographerRef.name.en',
      photographerNameKa: 'photographerRef.name.ka',
    },
    prepare({titleEn, titleKa, media, dateNote, photographerNameEn, photographerNameKa}) {
      const title = (titleEn || titleKa || 'Untitled photograph') as string
      const byline = (photographerNameEn || photographerNameKa || null) as string | null
      const parts = [byline ? `by ${byline}` : null, dateNote].filter(Boolean)

      return {
        title,
        subtitle: parts.join(' • '),
        media,
      }
    },
  },
})
