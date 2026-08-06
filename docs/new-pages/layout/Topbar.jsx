import { GiftIcon } from '../icons';

export default function Topbar() {
    return (
        <div className="topbar">
            <div className="container">
                <a className="notice" href="#">
                    <GiftIcon />
                    Free guide — 8 questions to ask before choosing an agent
                </a>
            </div>
        </div>
    );
}
