import LiveUpdates from './components/LiveUpdates';

function App() {
    console.log('App rendering with postId:', process.env.REACT_APP_POST_ID);
    return (
        <div className="App">
            <LiveUpdates postId={process.env.REACT_APP_POST_ID} />
        </div>
    );
}

export default App; 