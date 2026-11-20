import type { SVGAttributes } from 'react';
import { LogoMark } from '@/components/brand/logo';

export default function AppLogoIcon(
    props: SVGAttributes<SVGElement> & { accent?: string },
) {
    return <LogoMark {...props} />;
}
