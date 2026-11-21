import * as React from "react"

import { cn } from "@/lib/utils"

/** Multi-line input: 16px text, radius 8, same border/focus as <Input/>. */
function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "border-input placeholder:text-ink-muted flex min-h-28 w-full rounded-md border bg-surface px-3 py-2.5 text-base leading-6 text-ink transition-[color,background-color,border-color,box-shadow] outline-none hover:border-ink-muted/60 focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/20 disabled:cursor-not-allowed disabled:bg-raised disabled:opacity-70 aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive/15",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
