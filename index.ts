import photo from './photo'
import photographer from './photographer'
import collection from './collection'
import theme from './theme'
import curator from './curator'

import place from './place'
import tag from './tag'

import seo from './seo'
import localizedString from './localizedString'
import localizedText from './localizedText'

export const schemaTypes = [
  // objects
  seo,
  localizedString,
  localizedText,

  // documents
  photo,
  photographer,
  collection,
  theme,
  curator,
  place,
  tag,
]