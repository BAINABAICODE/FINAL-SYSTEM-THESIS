function ArrowIcon({ direction }) {
  const points = direction === 'prev' ? '15 6 9 12 15 18' : '9 6 15 12 9 18'
  return (
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
      <polyline
        points={points}
        fill="none"
        stroke="currentColor"
        strokeWidth="2.2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

export default function SliderControls({ species, activeIndex, onPrev, onNext, onGoTo }) {
  return (
    <div className="stage-controls">
      <button type="button" className="stage-controls__arrow" aria-label="Previous species" onClick={onPrev}>
        <ArrowIcon direction="prev" />
      </button>

      <div className="stage-controls__dots" role="tablist" aria-label="Species">
        {species.map((bird, index) => (
          <button
            key={bird.id}
            type="button"
            role="tab"
            aria-selected={index === activeIndex}
            aria-label={`Show ${bird.commonName}`}
            className={`stage-controls__dot${index === activeIndex ? ' is-active' : ''}`}
            onClick={() => onGoTo(index)}
          />
        ))}
      </div>

      <button type="button" className="stage-controls__arrow" aria-label="Next species" onClick={onNext}>
        <ArrowIcon direction="next" />
      </button>
    </div>
  )
}
