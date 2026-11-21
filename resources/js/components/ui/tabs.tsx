import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Lightweight accessible tabs (no extra dependency).
 *
 *   const [tab, setTab] = useState("bases")
 *   <Tabs value={tab} onValueChange={setTab}>
 *     <TabsList aria-label="Ish bo'limlari">
 *       <TabsTrigger value="bases">Asoslar</TabsTrigger>
 *       <TabsTrigger value="requests" count={4}>So'rovlar</TabsTrigger>
 *     </TabsList>
 *     <TabsContent value="bases">…</TabsContent>
 *   </Tabs>
 *
 * Style: underline tabs on a hairline; active = ink text + 2px brand underline.
 */
type Ctx = { value: string; setValue: (v: string) => void; baseId: string }

const TabsContext = React.createContext<Ctx | null>(null)

function useTabs(): Ctx {
  const ctx = React.useContext(TabsContext)

  if (!ctx) {
    throw new Error("Tabs components must be used inside <Tabs>")
  }

  return ctx
}

function Tabs({
  value,
  onValueChange,
  className,
  children,
}: {
  value: string
  onValueChange: (value: string) => void
  className?: string
  children: React.ReactNode
}) {
  const baseId = React.useId()

  return (
    <TabsContext.Provider value={{ value, setValue: onValueChange, baseId }}>
      <div data-slot="tabs" className={cn("flex flex-col gap-6", className)}>
        {children}
      </div>
    </TabsContext.Provider>
  )
}

function TabsList({ className, ...props }: React.ComponentProps<"div">) {
  const onKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") {
      return
    }

    const tabs = Array.from(
      e.currentTarget.querySelectorAll<HTMLButtonElement>('[role="tab"]:not([disabled])')
    )
    const i = tabs.indexOf(document.activeElement as HTMLButtonElement)
    const next = tabs[(i + (e.key === "ArrowRight" ? 1 : -1) + tabs.length) % tabs.length]
    next?.focus()
    next?.click()
    e.preventDefault()
  }

  return (
    <div
      role="tablist"
      data-slot="tabs-list"
      onKeyDown={onKeyDown}
      className={cn("flex gap-6 overflow-x-auto border-b border-hairline", className)}
      {...props}
    />
  )
}

function TabsTrigger({
  value,
  count,
  className,
  children,
  ...props
}: Omit<React.ComponentProps<"button">, "value"> & { value: string; count?: number }) {
  const ctx = useTabs()
  const selected = ctx.value === value

  return (
    <button
      type="button"
      role="tab"
      id={`${ctx.baseId}-tab-${value}`}
      aria-selected={selected}
      aria-controls={`${ctx.baseId}-panel-${value}`}
      tabIndex={selected ? 0 : -1}
      onClick={() => ctx.setValue(value)}
      data-state={selected ? "active" : "inactive"}
      className={cn(
        "relative -mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-2 text-base whitespace-nowrap transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand",
        selected
          ? "border-brand font-medium text-ink"
          : "border-transparent text-ink-secondary hover:text-ink",
        className
      )}
      {...props}
    >
      {children}
      {count !== undefined && (
        <span className="rounded-sm bg-raised px-1.5 text-meta text-ink-secondary tabular-nums">
          {count}
        </span>
      )}
    </button>
  )
}

function TabsContent({
  value,
  className,
  ...props
}: React.ComponentProps<"div"> & { value: string }) {
  const ctx = useTabs()

  if (ctx.value !== value) {
    return null
  }

  return (
    <div
      role="tabpanel"
      id={`${ctx.baseId}-panel-${value}`}
      aria-labelledby={`${ctx.baseId}-tab-${value}`}
      data-slot="tabs-content"
      className={cn("outline-none", className)}
      {...props}
    />
  )
}

export { Tabs, TabsList, TabsTrigger, TabsContent }
