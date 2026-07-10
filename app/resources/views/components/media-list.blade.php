@props(['images', 'ordered' => false])

@if ($images->isEmpty())
	{{ $empty ?? '' }}
@elseif ($ordered)
	<div {{ $attributes->class('media-list-grid grid items-start gap-3')->merge(['style' => 'grid-auto-rows: 8px']) }} x-data="{
		mutationObserver: null,
		refreshFrame: null,
		revealTimers: [],
		init() {
			this.mutationObserver = new MutationObserver(() => this.$nextTick(() => this.refresh()))
			this.mutationObserver.observe(this.$el, { childList: true })
			this.$nextTick(() => this.refresh())
		},
		destroy() {
			this.mutationObserver?.disconnect()
			if (this.refreshFrame) cancelAnimationFrame(this.refreshFrame)
			this.revealTimers.forEach((timer) => clearTimeout(timer))
		},
		refresh() {
			if (this.refreshFrame) return

			this.refreshFrame = requestAnimationFrame(() => {
				this.refreshFrame = null
				this.layout()
				this.reveal()
			})
		},
		layout() {
			const styles = getComputedStyle(this.$el)
			const rowHeight = Number.parseFloat(styles.gridAutoRows) || 8
			const rowGap = Number.parseFloat(styles.rowGap) || 0

			Array.from(this.$el.children).forEach((item) => {
				const span = Math.ceil((item.getBoundingClientRect().height + rowGap) / (rowHeight + rowGap))
				item.style.gridRowEnd = `span ${span}`
			})
		},
		orderedItems() {
			return Array.from(this.$el.children).sort((a, b) => {
				const aRect = a.getBoundingClientRect()
				const bRect = b.getBoundingClientRect()

				return Math.abs(aRect.top - bRect.top) > 8 ? aRect.top - bRect.top : aRect.left - bRect.left
			})
		},
		isReady(item) {
			const image = item.querySelector('img')

			return ! image || image.complete
		},
		watch(item) {
			const image = item.querySelector('img')

			if (! image || image.complete || item.dataset.watchingImage === 'true') return

			item.dataset.watchingImage = 'true'
			image.addEventListener('load', () => this.refresh(), { once: true })
			image.addEventListener('error', () => this.refresh(), { once: true })
		},
		reveal() {
			let delayIndex = 0

			for (const item of this.orderedItems().filter((item) => item.dataset.revealed !== 'true')) {
				if (! this.isReady(item)) {
					this.watch(item)

					break
				}

				this.show(item, delayIndex)
				delayIndex += 1
			}
		},
		show(item, index) {
			const delay = index * 70

			item.dataset.revealed = 'true'
			item.style.transitionDelay = `${delay}ms`
			requestAnimationFrame(() => item.classList.add('opacity-100!'))
			this.revealTimers.push(setTimeout(() => { item.style.transitionDelay = '' }, delay + 600))
		},
	}" x-on:load.capture.debounce.50ms="refresh()" x-on:media-item-loaded.capture.debounce.50ms="refresh()" x-on:resize.window.debounce.100ms="refresh()">
		{{ $slot }}
	</div>
@else
	<div {{ $attributes->class('media-list-columns gap-3') }}>
		{{ $slot }}
	</div>
@endif
