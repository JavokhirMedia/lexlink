import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-base font-medium transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-standard active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-[18px] [&_svg]:shrink-0 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-brand text-white shadow-xs hover:bg-brand-hover active:bg-brand-pressed",
        destructive:
          "bg-danger text-white shadow-xs hover:bg-danger/90",
        outline:
          "border border-input bg-surface text-ink shadow-xs hover:border-ink-muted/50 hover:bg-raised",
        secondary:
          "bg-raised text-ink hover:bg-hairline",
        ghost: "text-ink-secondary hover:bg-raised hover:text-ink",
        link: "h-auto px-0 text-brand underline-offset-4 hover:underline active:scale-100",
      },
      size: {
        default: "h-10 px-4 has-[>svg]:px-3.5",
        sm: "h-8 gap-1.5 px-3 text-sm has-[>svg]:px-2.5 [&_svg:not([class*='size-'])]:size-4",
        lg: "h-11 px-5 has-[>svg]:px-4",
        icon: "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
