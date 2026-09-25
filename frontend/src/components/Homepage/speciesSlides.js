import roseicollis from '../../assets/slides/roseicollis.png'
import fischeri from '../../assets/slides/fischeri.png'
import personatus from '../../assets/slides/personatus.png'
import lilianae from '../../assets/slides/lilianae.png'
import nigrigenis from '../../assets/slides/nigrigenis.png'
import canus from '../../assets/slides/canus.png'
import pullarius from '../../assets/slides/pullarius.png'
import taranta from '../../assets/slides/taranta.png'
import swindernianus from '../../assets/slides/swindernianus.png'

export const EYE_RING_GROUP = 'Eye-ring group'
export const NON_EYE_RING_GROUP = 'Non-eye-ring group'

/** Ordered to match the lovebird_species table ids used by the backend. */
export const SPECIES_SLIDES = [
  {
    id: 1,
    commonName: 'Peach-faced Lovebird',
    alternateName: 'Rosy-faced Lovebird',
    scientificName: 'Agapornis roseicollis',
    group: NON_EYE_RING_GROUP,
    description:
      'The most widely kept lovebird. A bright green body with a rosy-peach face and throat, horn-coloured beak, and vivid blue rump, with no white eye ring. Confident and playful, it forms strong pair bonds and carries the widest range of colour mutations of any Agapornis, making it central to genetic breeding work.',
    image: roseicollis,
  },
  {
    id: 2,
    commonName: "Fischer's Lovebird",
    alternateName: null,
    scientificName: 'Agapornis fischeri',
    group: EYE_RING_GROUP,
    description:
      'A compact, energetic species with a bright orange face, golden-yellow upper chest, green body, and blue rump. The bold white eye ring and red beak are its key identifiers. Sociable and hardy, it is one of the most frequently bred eye-ring lovebirds and hybridises readily with the Masked Lovebird.',
    image: fischeri,
  },
  {
    id: 3,
    commonName: 'Masked Lovebird',
    alternateName: 'Yellow-collared Lovebird',
    scientificName: 'Agapornis personatus',
    group: EYE_RING_GROUP,
    description:
      'Instantly recognisable by its dark brown-black head, bright yellow collar and chest, and green body. A white eye ring and red beak complete the “mask”. Playful and social, it is a classic eye-ring species with well-documented colour mutations such as blue and white.',
    image: personatus,
  },
  {
    id: 4,
    commonName: "Lilian's Lovebird",
    alternateName: 'Nyasa Lovebird',
    scientificName: 'Agapornis lilianae',
    group: EYE_RING_GROUP,
    description:
      'One of the smallest lovebirds. A salmon-orange face that fades to yellow on the chest, a bright green body, and a green (not blue) rump distinguish it from Fischer’s. It wears the white eye ring. Gentle and flock-oriented, it is considered near threatened in the wild and valued in conservation-minded breeding.',
    image: lilianae,
  },
  {
    id: 5,
    commonName: 'Black-cheeked Lovebird',
    alternateName: null,
    scientificName: 'Agapornis nigrigenis',
    group: EYE_RING_GROUP,
    description:
      'A small, dark-headed eye-ring species with brownish-black cheeks, a warm orange bib on the throat, and a rich green body. Closely related to the Masked Lovebird, it is calm and social. Wild populations are confined to south-west Zambia, so it is a conservation priority among breeders.',
    image: nigrigenis,
  },
  {
    id: 6,
    commonName: 'Madagascar Lovebird',
    alternateName: 'Grey-headed Lovebird',
    scientificName: 'Agapornis canus',
    group: NON_EYE_RING_GROUP,
    description:
      'The only lovebird native to Madagascar and one of the smallest. Strongly dimorphic: the male has a soft pale-grey head, neck, and chest on a green body, while the female is entirely green. It has no eye ring. Shy and quiet, it is uncommon in aviculture and sensitive to disturbance.',
    image: canus,
  },
  {
    id: 7,
    commonName: 'Red-headed Lovebird',
    alternateName: 'Red-faced Lovebird',
    scientificName: 'Agapornis pullarius',
    group: NON_EYE_RING_GROUP,
    description:
      'A slender lovebird with a bright red-orange face and forehead on a green body, a red beak, and a blue rump. Females show a paler orange face. Unusual among lovebirds, it nests inside termite mounds, which makes captive breeding notably difficult. It has no eye ring.',
    image: pullarius,
  },
  {
    id: 8,
    commonName: 'Abyssinian Lovebird',
    alternateName: 'Black-winged Lovebird',
    scientificName: 'Agapornis taranta',
    group: NON_EYE_RING_GROUP,
    description:
      'The largest lovebird and the only one from the Ethiopian highlands. Sexually dimorphic: the male shows a red forehead and lores with black underwings, while the female is plain green. Robust and relatively quiet, it tolerates cooler climates better than other species.',
    image: taranta,
  },
  {
    id: 9,
    commonName: 'Black-collared Lovebird',
    alternateName: "Swindern's Lovebird",
    scientificName: 'Agapornis swindernianus',
    group: NON_EYE_RING_GROUP,
    description:
      'A rare forest lovebird from Central and West Africa. Bright green with a narrow black collar across the nape edged in orange-yellow, a dark grey beak, and a blue-tinged rump. Dependent on native figs in the wild, it is almost never kept or bred in captivity and is rarely seen outside its range.',
    image: swindernianus,
  },
]
