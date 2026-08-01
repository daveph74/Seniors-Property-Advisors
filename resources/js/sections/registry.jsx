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
import StepsStripBlock from './StepsStripBlock';
import AvatarRowBlock from './AvatarRowBlock';
import RatingStarsBlock from './RatingStarsBlock';
import CardGridBlock from './CardGridBlock';
import StepGridBlock from './StepGridBlock';
import ChecklistBlock from './ChecklistBlock';
import BenefitListBlock from './BenefitListBlock';
import TrustMarksBlock from './TrustMarksBlock';
import StatStampBlock from './StatStampBlock';
import QuoteCardBlock from './QuoteCardBlock';
import InfoCardBlock from './InfoCardBlock';
import TextImageSection from './TextImageSection';
import StatRowSection from './StatRowSection';
import TestimonialsSection from './TestimonialsSection';
import FaqListSection from './FaqListSection';
import TeamIntroSection from './TeamIntroSection';
import ContactFormSection from './ContactFormSection';
import BlogListSection from './BlogListSection';

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
    'steps-strip': StepsStripBlock,
    'avatar-row': AvatarRowBlock,
    'rating-stars': RatingStarsBlock,
    'card-grid': CardGridBlock,
    'step-grid': StepGridBlock,
    checklist: ChecklistBlock,
    'benefit-list': BenefitListBlock,
    'trust-marks': TrustMarksBlock,
    'stat-stamp': StatStampBlock,
    'quote-card': QuoteCardBlock,
    'info-card': InfoCardBlock,
    'text-image': TextImageSection,
    'stat-row': StatRowSection,
    testimonials: TestimonialsSection,
    'faq-list': FaqListSection,
    'team-intro': TeamIntroSection,
    'contact-form': ContactFormSection,
    'blog-list': BlogListSection,
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
    'steps-strip': 'Steps strip',
    'avatar-row': 'Avatar row',
    'rating-stars': 'Star rating',
    'card-grid': 'Icon card grid',
    'step-grid': 'Numbered steps',
    checklist: 'Checklist',
    'benefit-list': 'Benefits list',
    'trust-marks': 'Trust marks',
    'stat-stamp': 'Stat badge',
    'quote-card': 'Quote card',
    'info-card': 'Info card',
    'text-image': 'Text and image',
    'stat-row': 'Statistics',
    testimonials: 'Testimonials',
    'faq-list': 'FAQs',
    'team-intro': 'Team introduction',
    'contact-form': 'Contact form',
    'blog-list': 'Blog articles',
};

export function resolveSection(type) {
    return SECTIONS[type] || null;
}
