import { Swiper, SwiperSlide } from 'swiper/react'
import { A11y, Autoplay, EffectCreative, Keyboard } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/effect-creative'
import './SpeciesSlider.css'

const AUTOPLAY_DELAY_MS = 6000

const CREATIVE_EFFECT = {
  limitProgress: 1,
  perspective: true,
  prev: {
    translate: ['-58%', '4%', -520],
    rotate: [0, 48, -4],
    opacity: 0,
  },
  next: {
    translate: ['58%', '4%', -520],
    rotate: [0, -48, 4],
    opacity: 0,
  },
}

export default function SpeciesSlider({ species, activeIndex, onActiveIndexChange, onSwiper }) {
  const handleSlideChange = (swiper) => {
    onActiveIndexChange(swiper.realIndex)
  }

  return (
    <div className="bird-stage">
      <Swiper
        className="bird-stage__swiper"
        modules={[EffectCreative, Autoplay, Keyboard, A11y]}
        effect="creative"
        creativeEffect={CREATIVE_EFFECT}
        slidesPerView={1}
        centeredSlides
        loop
        speed={820}
        grabCursor
        initialSlide={activeIndex}
        autoplay={{ delay: AUTOPLAY_DELAY_MS, pauseOnMouseEnter: true, disableOnInteraction: false }}
        keyboard={{ enabled: true }}
        a11y={{
          prevSlideMessage: 'Previous species',
          nextSlideMessage: 'Next species',
          slideLabelMessage: 'Species {{index}} of {{slidesLength}}',
        }}
        onSwiper={onSwiper}
        onSlideChange={handleSlideChange}
      >
        {species.map((bird, index) => (
          <SwiperSlide key={bird.id} className="bird-stage__slide">
            <figure className="bird-hero">
              <span className="bird-hero__halo" aria-hidden="true" />
              <span className="bird-hero__ring" aria-hidden="true" />
              <img
                className="bird-hero__image"
                src={bird.image}
                alt={`${bird.commonName} (${bird.scientificName})`}
                loading={index < 2 || index === species.length - 1 ? 'eager' : 'lazy'}
                decoding="async"
                draggable={false}
              />
              <span className="bird-hero__shadow" aria-hidden="true" />
              <figcaption className="bird-hero__caption">
                <span className="bird-hero__number">{String(index + 1).padStart(2, '0')}</span>
                <span className="bird-hero__name">{bird.commonName}</span>
              </figcaption>
            </figure>
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  )
}
