import { PASSWORD_RULES } from '../passwordPolicy';
import { CheckIcon, CrossIcon } from './icons';

export default function PasswordChecklist({ value }) {
    if (! value) {
        return null;
    }

    return (
        <ul className="cms-password-rules">
            {PASSWORD_RULES.map((rule) => {
                const met = rule.test(value);

                return (
                    <li
                        key={rule.id}
                        className={met ? 'cms-password-rules__item cms-password-rules__item--met' : 'cms-password-rules__item'}
                    >
                        <span className="cms-password-rules__mark">
                            {met ? <CheckIcon size={10} /> : <CrossIcon size={9} />}
                        </span>
                        {rule.label}
                    </li>
                );
            })}
        </ul>
    );
}
