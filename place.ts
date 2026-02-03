// schemaTypes/place.ts
import {defineType, defineField} from 'sanity'

export default defineType({
  name: 'place',
  title: 'Place',
  type: 'document',

  fields: [
    defineField({
      name: 'title',
      title: 'Preferred name (English)',
      type: 'string',
      validation: (Rule) => Rule.required().min(2).max(120),
    }),

    defineField({
      name: 'titleKa',
      title: 'Preferred name (Georgian)',
      type: 'string',
      validation: (Rule) => Rule.max(120),
    }),

    defineField({
      name: 'altNames',
      title: 'Alternative / historical names',
      type: 'array',
      of: [{type: 'string'}],
      description: 'Transliterations, old spellings, historical names (e.g., Tiflis).',
      validation: (Rule) => Rule.max(30).unique(),
    }),

    defineField({
      name: 'placeType',
      title: 'Place type',
      type: 'string',
      options: {
        list: [
          {title: 'Country', value: 'country'},
          {title: 'Region', value: 'region'},
          {title: 'City', value: 'city'},
          {title: 'Village', value: 'village'},
          {title: 'District / Neighborhood', value: 'district'},
          {title: 'Street', value: 'street'},
          {title: 'Building', value: 'building'},
          {title: 'Site / Landmark', value: 'site'},
          {title: 'Landscape / Natural', value: 'landscape'},
          {title: 'Other', value: 'other'},
        ],
      },
      validation: (Rule) => Rule.required(),
    }),

    defineField({
      name: 'parent',
      title: 'Parent place',
      type: 'reference',
      to: [{type: 'place'}],
      description: 'Build hierarchy (e.g., Building → City → Region → Country).',
    }),

    defineField({
      name: 'certainty',
      title: 'Attribution certainty',
      type: 'string',
      initialValue: 'unknown',
      options: {
        list: [
          {title: 'Exact / Confirmed', value: 'exact'},
          {title: 'Approximate', value: 'approximate'},
          {title: 'Disputed', value: 'disputed'},
          {title: 'Unknown', value: 'unknown'},
        ],
        layout: 'radio',
      },
      validation: (Rule) => Rule.required(),
    }),

    defineField({
      name: 'geo',
      title: 'Geo (optional)',
      type: 'geopoint',
      description: 'Optional. Use only when known with confidence.',
    }),

    defineField({
      name: 'notes',
      title: 'Curatorial notes / sources',
      type: 'text',
      rows: 4,
      description: 'Evidence, reasoning, or references supporting this place attribution.',
      validation: (Rule) => Rule.max(2000),
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
    select: {title: 'title', type: 'placeType', ka: 'titleKa'},
    prepare({title, type, ka}) {
      const t = type ? `(${type})` : ''
      const kaPart = ka ? ` • ${ka}` : ''
      return {title: `${title} ${t}`.trim(), subtitle: kaPart.trim()}
    },
  },
})
