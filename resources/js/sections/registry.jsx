import HeroSection from './HeroSection';
import TrustCardsSection from './TrustCardsSection';
import ProcessStepsSection from './ProcessStepsSection';
import WhyListSection from './WhyListSection';
import AgentCompareSection from './AgentCompareSection';
import FamilySection from './FamilySection';
import CtaSection from './CtaSection';
import SectionContainer from './SectionContainer';
import RowContainer from './RowContainer';
import ColumnContainer from './ColumnContainer';
import EyebrowBlock from './EyebrowBlock';
import HeadingBlock from './HeadingBlock';
import RichTextBlock from './RichTextBlock';
import ImageBlock from './ImageBlock';
import ButtonBlock from './ButtonBlock';

export const SECTIONS = {
    section: SectionContainer,
    row: RowContainer,
    column: ColumnContainer,
    hero: HeroSection,
    'trust-cards': TrustCardsSection,
    'process-steps': ProcessStepsSection,
    'why-list': WhyListSection,
    'agent-compare': AgentCompareSection,
    family: FamilySection,
    cta: CtaSection,
    eyebrow: EyebrowBlock,
    heading: HeadingBlock,
    'rich-text': RichTextBlock,
    image: ImageBlock,
    button: ButtonBlock,
};

export const SECTION_LABELS = {
    section: 'Section',
    row: 'Row',
    column: 'Column',
    hero: 'Hero banner',
    'trust-cards': 'Trust cards',
    'process-steps': 'Process steps',
    'why-list': 'Why list',
    'agent-compare': 'Agent comparison',
    family: 'For families',
    cta: 'Call to action',
    eyebrow: 'Pre-heading',
    heading: 'Heading',
    'rich-text': 'Rich text',
    image: 'Image',
    button: 'Button',
};

export function resolveSection(type) {
    return SECTIONS[type] || null;
}
