// schemaTypes/seo.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'seo',
  title: 'SEO',
  type: 'object',
  description:
    'Reusable page metadata for better search indexing and link previews (Theme/Collection/Photographer pages).',

  fields: [
    defineField({
      name: 'metaTitle',
      title: 'Meta title',
      type: 'string',
      description: 'Shown in browser tab and search results (when used by the frontend).',
      validation: (Rule) => Rule.max(60),
    }),

    defineField({
      name: 'metaDescription',
      title: 'Meta description',
      type: 'text',
      rows: 3,
      description: 'Short summary for search results and sharing previews.',
      validation: (Rule) => Rule.max(160),
    }),

    defineField({
      name: 'ogImage',
      title: 'Social share image (Open Graph)',
      type: 'image',
      options: {hotspot: true},
    }),

    defineField({
      name: 'noIndex',
      title: 'No-index',
      type: 'boolean',
      description: 'If true, frontend can set robots noindex for internal/non-public pages.',
      initialValue: false,
    }),
  ],
})
