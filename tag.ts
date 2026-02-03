// schemaTypes/tag.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'tag',
  title: 'Tag',
  type: 'document',
  description:
    'Controlled vocabulary for faceted search (cross-cutting labels like techniques, formats, objects). Distinct from the hierarchical Themes taxonomy.',

  fields: [
    defineField({
      name: 'title',
      title: 'Title (English)',
      type: 'string',
      validation: (Rule) => Rule.required().min(2).max(80),
    }),

    defineField({
      name: 'titleKa',
      title: 'Title (Georgian)',
      type: 'string',
      validation: (Rule) => Rule.max(80),
    }),

    defineField({
      name: 'scope',
      title: 'Scope',
      type: 'string',
      description: 'Helps keep tags organized and improves filtering UX.',
      options: {
        list: [
          {title: 'Subject / Object', value: 'subject'},
          {title: 'Technique / Process', value: 'technique'},
          {title: 'Format / Medium', value: 'format'},
          {title: 'Clothing / Uniform', value: 'uniform'},
          {title: 'Architecture / Built Environment', value: 'architecture'},
          {title: 'Event attribute', value: 'event'},
          {title: 'Other', value: 'other'},
        ],
      },
      validation: (Rule) => Rule.required(),
    }),

    defineField({
      name: 'altLabels',
      title: 'Alternative labels (synonyms)',
      type: 'array',
      of: [{type: 'string'}],
      description: 'Search help: synonyms, spelling variants, transliterations.',
      validation: (Rule) => Rule.max(20).unique(),
    }),

    defineField({
      name: 'slug',
      title: 'Slug',
      type: 'slug',
      options: {source: 'title', maxLength: 96},
      validation: (Rule) => Rule.required(),
    }),
  ],

  preview: {
    select: {title: 'title', scope: 'scope', ka: 'titleKa'},
    prepare({title, scope, ka}) {
      const s = scope ? `(${scope})` : ''
      const kaPart = ka ? ` • ${ka}` : ''
      return {title: `${title} ${s}`.trim(), subtitle: kaPart.trim()}
    },
  },
})
