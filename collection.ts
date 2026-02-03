import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'collection',
  title: 'Collection / Archive',
  type: 'document',

  groups: [
    {name: 'basic', title: 'Basic Info'},
    {name: 'hierarchy', title: 'Hierarchy'}, // NEW
    {name: 'provenance', title: 'Provenance & Sources'},
    {name: 'content', title: 'Content & Description'},
    {name: 'technical', title: 'Technical'},
  ],

  fields: [
    defineField({
      name: 'title',
      title: 'Collection title',
      type: 'localizedString',
      group: 'basic',
      description: 'Public-facing collection name (bilingual).',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (!en && !ka) return 'Title is required in at least one language (EN or KA).'
          if (en && (en.length < 2 || en.length > 140)) return 'English title must be 2–140 characters.'
          if (ka && (ka.length < 2 || ka.length > 140)) return 'Georgian title must be 2–140 characters.'
          return true
        }),
    }),

    defineField({
      name: 'slug',
      title: 'Slug',
      type: 'slug',
      group: 'basic',
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

    // ============================================
    // NEW: HIERARCHY SUPPORT
    // ============================================

    defineField({
      name: 'parent',
      title: 'Parent collection (optional)',
      type: 'reference',
      to: [{type: 'collection'}],
      group: 'hierarchy',
      description: 'If this is a sub-collection, link to its parent collection.',
      validation: (Rule) =>
        Rule.custom((value, context) => {
          const parentId = value?._ref
          const currentId = (context.document as any)?._id
          
          // Prevent self-reference
          if (parentId === currentId) {
            return 'A collection cannot be its own parent.'
          }
          
          return true
        }),
    }),

    defineField({
      name: 'sortOrder',
      title: 'Sort order (within parent)',
      type: 'number',
      group: 'hierarchy',
      description: 'Optional: control display order of sub-collections (lower numbers first).',
      validation: (Rule) => Rule.integer().min(0).max(9999),
      hidden: ({parent}) => !parent?.parent, // Only show if has parent
    }),

    // ============================================
    // COLLECTION TYPE CLASSIFICATION
    // ============================================

    defineField({
      name: 'collectionType',
      title: 'Collection type',
      type: 'string',
      group: 'provenance',
      options: {
        list: [
          {
            title: 'Family Donation (Family Album)',
            value: 'family_donation',
          },
          {
            title: 'Institutional Acquisition (Museum/Archive Transfer)',
            value: 'institutional_acquisition',
          },
          {
            title: 'Photographer Estate (Photographer\'s Body of Work)',
            value: 'photographer_estate',
          },
          {
            title: 'Organizational Archive (Society/Studio Collection)',
            value: 'organizational_archive',
          },
          {
            title: 'Curatorial Project (Research Assembly from Multiple Sources)',
            value: 'curatorial_project',
          },
          {
            title: 'Technical Grouping (Process/Technique Study)',
            value: 'technical_grouping',
          },
          {
            title: 'Parent Container (No Photos - Only Sub-Collections)', // NEW
            value: 'parent_container',
          },
        ],
        layout: 'radio',
      },
      initialValue: 'family_donation',
      validation: (Rule) => Rule.required(),
      description: 'Critical: determines if this is original provenance or curatorial assembly.',
    }),

    // ============================================
    // PROVENANCE DISTINCTION
    // ============================================

    defineField({
      name: 'isOriginalGrouping',
      title: 'Original grouping? (Provenance vs. Curatorial)',
      type: 'boolean',
      group: 'provenance',
      initialValue: true,
      description:
        'TRUE = Museum received this as an intact unit (provenance). FALSE = Curator assembled from multiple sources (curatorial project).',
    }),

    defineField({
      name: 'curatedBy',
      title: 'Curated by (if curatorial project)',
      type: 'reference',
      to: [{type: 'curator'}],
      group: 'provenance',
      hidden: ({parent}) => parent?.isOriginalGrouping !== false,
      description: 'For curatorial projects: who assembled this collection?',
    }),

    defineField({
      name: 'sources',
      title: 'Source(s)',
      type: 'array',
      of: [{type: 'string'}],
      group: 'provenance',
      description:
        'For provenance collections: single source. For curatorial projects: list all contributing archives/sources.',
      validation: (Rule) => Rule.max(20),
    }),

    // ============================================
    // ACQUISITION CONTEXT
    // ============================================

    defineField({
      name: 'acquisitionYear',
      title: 'Acquisition year',
      type: 'number',
      group: 'provenance',
      description: 'Year museum acquired this collection (optional).',
      validation: (Rule) => Rule.integer().min(1840).max(2100),
    }),

    defineField({
      name: 'acquisitionNote',
      title: 'Acquisition/Research note',
      type: 'localizedText',
      group: 'provenance',
      description:
        'For provenance: how museum acquired. For curatorial: research methodology, why assembled.',
    }),

    // ============================================
    // CONTENT FIELDS
    // ============================================

    defineField({
      name: 'ownerOrCollector',
      title: 'Original owner / Collector / Family (label)',
      type: 'localizedString',
      group: 'content',
      description: 'Display label for provenance/holder (bilingual).',
      validation: (Rule) => Rule.custom(() => true),
    }),

    defineField({
      name: 'dateRangeNote',
      title: 'Date range note (materials)',
      type: 'localizedString',
      group: 'content',
      description: 'Optional. Example: "1860s–1930s" or "late 19th century".',
      validation: (Rule) =>
        Rule.custom((value: any) => {
          const en = (value?.en || '').trim()
          const ka = (value?.ka || '').trim()
          if (en.length > 120) return 'English date note must be 120 characters or fewer.'
          if (ka.length > 120) return 'Georgian date note must be 120 characters or fewer.'
          return true
        }),
    }),

    defineField({
      name: 'description',
      title: 'Public description',
      type: 'localizedText',
      group: 'content',
      description: 'Public-facing overview: provenance, significance, what it contains (bilingual).',
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
      name: 'curatorialNotes',
      title: 'Curatorial notes (internal)',
      type: 'text',
      group: 'content',
      rows: 5,
      description: 'Internal research notes, uncertain attributions, sources, etc.',
      validation: (Rule) => Rule.max(3000),
    }),

    defineField({
      name: 'coverImage',
      title: 'Cover image (optional)',
      type: 'image',
      group: 'technical',
      options: {hotspot: true},
      description: 'Optional representative image for collection landing pages.',
      fields: [
        defineField({
          name: 'alt',
          title: 'Alt text',
          type: 'localizedString',
          description: 'Bilingual alt text for accessibility.',
        }),
      ],
    }),
  ],

  preview: {
    select: {
      titleEn: 'title.en',
      titleKa: 'title.ka',
      collectionType: 'collectionType',
      isOriginalGrouping: 'isOriginalGrouping',
      parentTitleEn: 'parent.title.en',
      parentTitleKa: 'parent.title.ka',
      media: 'coverImage',
    },
    prepare({titleEn, titleKa, collectionType, isOriginalGrouping, parentTitleEn, parentTitleKa, media}) {
      const title = titleEn || titleKa || 'Untitled collection'
      const parentTitle = parentTitleEn || parentTitleKa

      // Icon prefix based on type
      const icon = isOriginalGrouping ? '📦' : '🎨'
      
      // Add folder icon if parent container
      const folderIcon = collectionType === 'parent_container' ? '📁 ' : ''

      const typeLabel =
        collectionType === 'family_donation'
          ? 'Family Donation'
          : collectionType === 'institutional_acquisition'
            ? 'Institutional'
            : collectionType === 'photographer_estate'
              ? 'Photographer Estate'
            : collectionType === 'organizational_archive'
              ? 'Organizational Archive'
              : collectionType === 'curatorial_project'
                ? 'Curatorial Project'
                : collectionType === 'technical_grouping'
                  ? 'Technical Grouping'
                  : collectionType === 'parent_container'
                    ? 'Parent Container'
                    : 'Other'

      const provenanceLabel = isOriginalGrouping ? 'Provenance' : 'Curatorial'
      
      // Show parent in subtitle if sub-collection
      const parentInfo = parentTitle ? ` • Parent: ${parentTitle}` : ''

      return {
        title: `${folderIcon}${icon} ${title}`,
        subtitle: `${provenanceLabel} • ${typeLabel}${parentInfo}`,
        media,
      }
    },
  },
})