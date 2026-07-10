@props(['images'])

@if ($images->isEmpty())
	{{ $empty ?? '' }}
@else
	<div
		x-data="{
			resizeObserver: null,
			mutationObserver: null,
			resize(item) {
				const rowHeight = 4
				const gap = parseFloat(getComputedStyle(this.$el).rowGap)
				const span = Math.ceil((item.getBoundingClientRect().height + gap) / (rowHeight + gap))
				const gridRowEnd = `span ${span}`

				if (item.style.gridRowEnd !== gridRowEnd) item.style.gridRowEnd = gridRowEnd
			},
			layout(items) {
				Array.from(items).filter((item) => item instanceof HTMLElement).forEach((item) => {
					this.resizeObserver.observe(item)
					this.resize(item)
				})
			},
			init() {
				this.resizeObserver = new ResizeObserver((entries) => entries.forEach(({ target }) => this.resize(target)))
				this.mutationObserver = new MutationObserver((entries) => this.layout(entries.flatMap(({ addedNodes }) => Array.from(addedNodes))))
				this.mutationObserver.observe(this.$el, { childList: true })
				this.layout(this.$el.children)
			},
			destroy() {
				this.resizeObserver.disconnect()
				this.mutationObserver.disconnect()
			},
		}"
		x-bind:style="{ gridAutoRows: '4px' }"
		{{ $attributes->class('grid grid-flow-row grid-cols-2 items-start gap-x-4 gap-y-3 [&>*]:mb-0 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8') }}
	>
		{{ $slot }}
	</div>
@endif