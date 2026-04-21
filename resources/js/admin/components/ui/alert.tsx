import { cn } from "@/lib/utils";
import { type VariantProps, cva } from "class-variance-authority";
import type { HTMLAttributes } from "react";

const alertVariants = cva(
    "relative w-full rounded-lg border px-4 py-3 text-sm [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground [&>svg~*]:pl-7",
    {
        variants: {
            variant: {
                default: "bg-background text-foreground",
                destructive:
                    "border-destructive/50 text-destructive dark:border-destructive [&>svg]:text-destructive",
                warning:
                    "border-yellow-500/50 text-yellow-800 dark:text-yellow-200 [&>svg]:text-yellow-600",
            },
        },
        defaultVariants: {
            variant: "default",
        },
    }
);

const Alert = ({
    className,
    variant,
    ...props
}: HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants>) => (
    <div role="alert" className={cn(alertVariants({ variant }), className)} {...props} />
);
Alert.displayName = "Alert";

const AlertTitle = ({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) => (
    <h5 className={cn("mb-1 font-medium leading-none tracking-tight", className)} {...props} />
);
AlertTitle.displayName = "AlertTitle";

const AlertDescription = ({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) => (
    <div className={cn("text-sm [&_p]:leading-relaxed", className)} {...props} />
);
AlertDescription.displayName = "AlertDescription";

export { Alert, AlertTitle, AlertDescription };
