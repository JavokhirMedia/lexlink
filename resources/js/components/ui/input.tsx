import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "border-input file:text-foreground placeholder:text-ink-muted flex h-11 w-full min-w-0 rounded-md border bg-surface px-3 py-2 text-base text-ink transition-[color,background-color,border-color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium hover:border-ink-muted/60 disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-raised disabled:opacity-70",
        "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/20",
        "aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive/15",
        className
      )}
      {...props}
    />
  )
}

export { Input }
