import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import './SearchableSelect.css'

const PREVIEW_WIDTH = 300
const PREVIEW_HEIGHT = 300

function optionBlock(option, isOptionDisabled) {
  if (!option || typeof isOptionDisabled !== 'function') {
    return { blocked: false, reason: '' }
  }

  const result = isOptionDisabled(option)
  if (result === true) return { blocked: true, reason: '' }
  if (typeof result === 'string' && result.trim()) return { blocked: true, reason: result.trim() }
  return { blocked: false, reason: '' }
}

function placePreviewBeside(menuRect, optionRect) {
  const gap = 8
  const pad = 8
  const menu = menuRect || optionRect
  const option = optionRect || menuRect
  if (!menu || !option) return null

  const rightLeft = menu.right + gap
  const leftLeft = menu.left - gap - PREVIEW_WIDTH
  const fitsRight = rightLeft + PREVIEW_WIDTH <= window.innerWidth - pad
  const fitsLeft = leftLeft >= pad

  let left = rightLeft
  if (!fitsRight && fitsLeft) {
    left = leftLeft
  } else if (!fitsRight) {
    left = Math.max(pad, window.innerWidth - PREVIEW_WIDTH - pad)
    if (left < menu.right && left + PREVIEW_WIDTH > menu.left) {
      left = fitsLeft ? leftLeft : pad
    }
  }

  let top = option.top
  const maxTop = Math.max(pad, window.innerHeight - PREVIEW_HEIGHT - pad)
  if (top > maxTop) top = maxTop
  if (top < pad) top = pad

  return {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
    width: `${PREVIEW_WIDTH}px`,
  }
}

export default function SearchableSelect({
  label,
  options = [],
  value,
  onChange,
  getOptionLabel = (option) => option?.name ?? '',
  getOptionValue = (option) => option?.id ?? null,
  placeholder = 'Select…',
  searchPlaceholder = 'Search…',
  allowEmpty = true,
  emptyLabel = 'None',
  disabled = false,
  required = false,
  error = '',
  readable = false,
  getOptionPreview = null,
  multiple = false,
  isOptionDisabled = null,
}) {
  const reactId = useId()
  const rootRef = useRef(null)
  const triggerRef = useRef(null)
  const menuRef = useRef(null)
  const searchRef = useRef(null)
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)
  const [menuStyle, setMenuStyle] = useState({})
  const [hoveredOption, setHoveredOption] = useState(null)
  const [previewStyle, setPreviewStyle] = useState(null)
  const hidePreviewTimer = useRef(null)
  const previewRef = useRef(null)

  const selectedValues = useMemo(() => {
    if (!multiple) return []
    const list = Array.isArray(value) ? value : []
    return list.filter((item) => item !== null && item !== undefined && item !== '')
  }, [multiple, value])

  const selected = useMemo(() => {
    if (multiple) return null
    return options.find((option) => String(getOptionValue(option)) === String(value ?? '')) ?? null
  }, [options, value, getOptionValue, multiple])

  const selectedOptions = useMemo(() => {
    if (!multiple) return []
    return selectedValues
      .map((id) => options.find((option) => String(getOptionValue(option)) === String(id)) ?? null)
      .filter(Boolean)
  }, [multiple, selectedValues, options, getOptionValue])

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase()
    if (!term) return options
    return options.filter((option) => getOptionLabel(option).toLowerCase().includes(term))
  }, [options, query, getOptionLabel])

  const items = useMemo(() => {
    const list = filtered.map((option) => ({
      key: String(getOptionValue(option)),
      value: getOptionValue(option),
      label: getOptionLabel(option),
      option,
    }))

    if (allowEmpty) {
      return [{ key: '__empty__', value: null, label: emptyLabel, option: null }, ...list]
    }

    return list
  }, [filtered, allowEmpty, emptyLabel, getOptionLabel, getOptionValue])

  useLayoutEffect(() => {
    if (!open || !triggerRef.current) return undefined

    const updatePosition = () => {
      const rect = triggerRef.current.getBoundingClientRect()
      const viewportPadding = 8
      const menuHeight = Math.min(readable ? 340 : 248, window.innerHeight - viewportPadding * 2)
      const spaceBelow = window.innerHeight - rect.bottom - viewportPadding
      const openUpward = spaceBelow < 180 && rect.top > spaceBelow

      setMenuStyle({
        position: 'fixed',
        left: `${Math.max(viewportPadding, rect.left)}px`,
        width: `${rect.width}px`,
        top: openUpward ? 'auto' : `${rect.bottom + 6}px`,
        bottom: openUpward ? `${window.innerHeight - rect.top + 6}px` : 'auto',
        maxHeight: `${
          openUpward
            ? Math.min(menuHeight, rect.top - viewportPadding)
            : Math.min(menuHeight, spaceBelow)
        }px`,
        zIndex: 120,
      })
    }

    updatePosition()
    window.addEventListener('resize', updatePosition)
    window.addEventListener('scroll', updatePosition, true)
    return () => {
      window.removeEventListener('resize', updatePosition)
      window.removeEventListener('scroll', updatePosition, true)
    }
  }, [open, query, items.length, readable])

  useEffect(() => {
    if (!open) return undefined

    const onPointerDown = (event) => {
      if (
        !rootRef.current?.contains(event.target) &&
        !menuRef.current?.contains(event.target) &&
        !previewRef.current?.contains(event.target)
      ) {
        setOpen(false)
        setQuery('')
      }
    }

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        setOpen(false)
        setQuery('')
      }
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  useEffect(() => {
    if (open) {
      setActiveIndex(0)
      setHoveredOption(null)
      setPreviewStyle(null)
      window.requestAnimationFrame(() => searchRef.current?.focus())
    } else {
      setHoveredOption(null)
      setPreviewStyle(null)
    }
  }, [open])

  useEffect(() => () => {
    if (hidePreviewTimer.current) window.clearTimeout(hidePreviewTimer.current)
  }, [])

  const preview = hoveredOption && getOptionPreview ? getOptionPreview(hoveredOption) : null

  const cancelHidePreview = () => {
    if (hidePreviewTimer.current) {
      window.clearTimeout(hidePreviewTimer.current)
      hidePreviewTimer.current = null
    }
  }

  const hidePreviewSoon = () => {
    cancelHidePreview()
    hidePreviewTimer.current = window.setTimeout(() => {
      setHoveredOption(null)
      setPreviewStyle(null)
    }, 160)
  }

  const showPreviewFor = (item, button) => {
    cancelHidePreview()
    if (!getOptionPreview || !item?.option || !button) {
      setHoveredOption(null)
      setPreviewStyle(null)
      return
    }
    setHoveredOption(item.option)
    const menuRect = menuRef.current?.getBoundingClientRect()
    const optionRect = button.getBoundingClientRect()
    setPreviewStyle(placePreviewBeside(menuRect, optionRect))
  }

  const selectItem = (item) => {
    const block = optionBlock(item.option, isOptionDisabled)
    const alreadySelected = multiple
      && item.value !== null
      && selectedValues.some((id) => String(id) === String(item.value))
    if (block.blocked && !alreadySelected) return

    if (!multiple) {
      onChange(item.value)
      setOpen(false)
      setQuery('')
      return
    }

    if (item.value === null) {
      onChange([])
      setOpen(false)
      setQuery('')
      return
    }

    const exists = selectedValues.some((id) => String(id) === String(item.value))
    const next = exists
      ? selectedValues.filter((id) => String(id) !== String(item.value))
      : [...selectedValues, item.value]
    onChange(next)
  }

  const onTriggerKeyDown = (event) => {
    if (disabled) return
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      setOpen(true)
    }
  }

  const onListKeyDown = (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((index) => Math.min(index + 1, items.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((index) => Math.max(index - 1, 0))
    } else if (event.key === 'Enter') {
      event.preventDefault()
      if (items[activeIndex]) selectItem(items[activeIndex])
    }
  }

  return (
    <div
      className={`search-select${readable ? ' search-select--readable' : ''}${error ? ' has-error' : ''}${disabled ? ' is-disabled' : ''}`}
      ref={rootRef}
    >
      {label ? (
        <span className="search-select__label" id={`${reactId}-label`}>
          {label}
          {required ? <span className="search-select__required"> *</span> : null}
        </span>
      ) : null}

      <button
        type="button"
        ref={triggerRef}
        className={`search-select__trigger${open ? ' is-open' : ''}${
          multiple ? (selectedOptions.length ? ' has-value' : '') : selected ? ' has-value' : ''
        }`}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-labelledby={label ? `${reactId}-label` : undefined}
        disabled={disabled}
        onClick={() => {
          if (!disabled) setOpen((current) => !current)
        }}
        onKeyDown={onTriggerKeyDown}
      >
        <span className="search-select__value">
          {multiple
            ? selectedOptions.length
              ? selectedOptions.map((option) => getOptionLabel(option)).join(', ')
              : allowEmpty
                ? emptyLabel
                : placeholder
            : selected
              ? getOptionLabel(selected)
              : allowEmpty
                ? emptyLabel
                : placeholder}
        </span>
        <span className="search-select__caret" aria-hidden="true" />
      </button>

      {open ? (
        <div className="search-select__menu" role="presentation" ref={menuRef} style={menuStyle}>
          <div className="search-select__search">
            <input
              ref={searchRef}
              type="search"
              value={query}
              placeholder={searchPlaceholder}
              aria-label={searchPlaceholder}
              onChange={(event) => {
                setQuery(event.target.value)
                setActiveIndex(0)
              }}
              onKeyDown={onListKeyDown}
            />
          </div>

          <ul
            className="search-select__list"
            role="listbox"
            aria-labelledby={label ? `${reactId}-label` : undefined}
          >
            {items.length === 0 ? (
              <li className="search-select__empty">No matches</li>
            ) : (
              items.map((item, index) => {
                const isSelected = multiple
                  ? item.value === null
                    ? selectedValues.length === 0
                    : selectedValues.some((id) => String(id) === String(item.value))
                  : (item.value === null && (value === null || value === '' || value === undefined)) ||
                    String(item.value) === String(value)
                const block = optionBlock(item.option, isOptionDisabled)
                const isDisabled = block.blocked && !isSelected

                return (
                  <li key={item.key}>
                    <button
                      type="button"
                      role="option"
                      data-option-index={index}
                      aria-selected={isSelected}
                      aria-disabled={isDisabled}
                      disabled={isDisabled}
                      title={block.reason || undefined}
                      className={`search-select__option${isSelected ? ' is-selected' : ''}${
                        index === activeIndex ? ' is-active' : ''
                      }${isDisabled ? ' is-disabled' : ''}`}
                      onMouseEnter={(event) => {
                        setActiveIndex(index)
                        showPreviewFor(item, event.currentTarget)
                      }}
                      onMouseLeave={hidePreviewSoon}
                      onFocus={(event) => {
                        setActiveIndex(index)
                        showPreviewFor(item, event.currentTarget)
                      }}
                      onBlur={hidePreviewSoon}
                      onClick={() => selectItem(item)}
                    >
                      <span className="search-select__option-copy">
                        <span className="search-select__option-label">{item.label}</span>
                        {block.reason ? (
                          <span className="search-select__option-note">{block.reason}</span>
                        ) : null}
                      </span>
                      <span
                        className={`search-select__mark${isSelected ? ' is-on' : ''}`}
                        aria-hidden="true"
                      >
                        {isSelected ? (
                          <svg viewBox="0 0 16 16" width="12" height="12" focusable="false">
                            <path
                              d="M3.2 8.2 6.1 11l6.7-7.2"
                              fill="none"
                              stroke="currentColor"
                              strokeWidth="2.1"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            />
                          </svg>
                        ) : null}
                      </span>
                    </button>
                  </li>
                )
              })
            )}
          </ul>
        </div>
      ) : null}

      {preview?.src && previewStyle
        ? createPortal(
            <div
              ref={previewRef}
              className="species-preview"
              style={previewStyle}
              onMouseEnter={cancelHidePreview}
              onMouseLeave={hidePreviewSoon}
            >
              <img src={preview.src} alt={preview.alt || ''} />
            </div>,
            document.body,
          )
        : null}

      {error ? <span className="search-select__error">{error}</span> : null}
    </div>
  )
}
